<?php
declare(strict_types=1);

final class ReceiptVerificationService
{
    public static function verify(array $payment, array $ocr, float $expectedAmount, array $billing): array
    {
        $parsed = $ocr['parsed'] ?? [];
        $text = strtolower((string) ($ocr['text'] ?? ''));
        $checks = [];

        $readable = !empty($ocr['readable']);
        $checks['readable'] = [
            'ok' => $readable,
            'message' => $readable ? 'Receipt text could be read.' : 'The uploaded file could not be read clearly.',
        ];

        $looksLike = !empty($parsed['looks_like_receipt']);
        $checks['is_receipt'] = [
            'ok' => $looksLike,
            'message' => $looksLike ? 'Document appears to be a payment receipt.' : 'Document does not look like a payment receipt.',
        ];

        $extractedAmount = isset($parsed['amount']) ? (float) $parsed['amount'] : null;
        $amountOk = $extractedAmount !== null && abs($extractedAmount - $expectedAmount) < 0.51;
        $checks['amount'] = [
            'ok' => $amountOk,
            'message' => $amountOk
                ? 'Paid amount matches the expected subscription amount.'
                : ($extractedAmount === null
                    ? 'Paid amount could not be extracted.'
                    : 'Paid amount ' . money($extractedAmount) . ' does not match ' . money($expectedAmount) . '.'),
            'extracted' => $extractedAmount,
            'expected' => $expectedAmount,
        ];

        $date = $parsed['date'] ?? null;
        $dateOk = false;
        $dateMessage = 'Payment date could not be extracted.';
        if ($date) {
            $ts = strtotime($date . ' 12:00:00');
            $min = strtotime('-45 days');
            $max = strtotime('+1 day');
            if ($ts === false) {
                $dateMessage = 'Extracted payment date is invalid.';
            } elseif ($ts > $max) {
                $dateMessage = 'Payment date is in the future.';
            } elseif ($ts < $min) {
                $dateMessage = 'Payment date is too old for this renewal.';
            } else {
                $dateOk = true;
                $dateMessage = 'Payment date is valid.';
            }
        }
        $checks['date'] = ['ok' => $dateOk, 'message' => $dateMessage, 'extracted' => $date];

        $reference = $parsed['reference'] ?? ($payment['payment_reference'] ?? null);
        $refOk = is_string($reference) && strlen(trim($reference)) >= 4;
        $checks['reference'] = [
            'ok' => $refOk,
            'message' => $refOk ? 'A transaction/reference number was found.' : 'No transaction/reference number was found.',
            'extracted' => $reference,
        ];

        $account = preg_replace('/\s+/', '', (string) ($billing['account_number'] ?? ''));
        $company = strtolower((string) ($billing['company_name'] ?? ''));
        $accountName = strtolower((string) ($billing['account_name'] ?? ''));
        $recipientHay = strtolower((string) (($parsed['recipient'] ?? '') . ' ' . $text));
        $accountMatch = $account !== '' && str_contains(preg_replace('/\s+/', '', $text), $account);
        $nameMatch = ($company !== '' && str_contains($recipientHay, $company))
            || ($accountName !== '' && str_contains($recipientHay, $accountName));
        $bankMatch = $accountMatch || $nameMatch;
        $checks['recipient'] = [
            'ok' => $bankMatch,
            'message' => $bankMatch
                ? 'Payment appears to be made to the company account.'
                : 'Could not confirm the payment was made to the company account.',
        ];

        $duplicateRef = $refOk && SubscriptionService::isDuplicateReference((string) $reference, (int) ($payment['payment_id'] ?? 0) ?: null);
        $duplicateFile = !empty($payment['receipt_hash'])
            && SubscriptionService::isDuplicateReceipt((string) $payment['receipt_hash'], (int) ($payment['payment_id'] ?? 0) ?: null);
        $checks['duplicate'] = [
            'ok' => !$duplicateRef && !$duplicateFile,
            'message' => ($duplicateRef || $duplicateFile)
                ? 'This transaction or receipt was already used for a renewal.'
                : 'No duplicate transaction or receipt was found.',
        ];

        $inconsistent = false;
        if ($extractedAmount !== null && !$amountOk && $looksLike) {
            $inconsistent = true;
        }
        $checks['consistency'] = [
            'ok' => !$inconsistent,
            'message' => $inconsistent ? 'Extracted details are inconsistent with the expected payment.' : 'No suspicious inconsistencies were found.',
        ];

        $hardFail = (!$checks['duplicate']['ok'])
            || ($extractedAmount !== null && !$amountOk)
            || ($readable && $looksLike === false && empty($ocr['text']) === false && strlen($text) > 40);

        $strongPass = $readable && $looksLike && $amountOk && $dateOk && $checks['duplicate']['ok']
            && ($refOk || $bankMatch);

        $confidence = 0;
        foreach ($checks as $check) {
            if (!empty($check['ok'])) {
                $confidence += 12;
            }
        }
        if ($strongPass) {
            $confidence = max($confidence, 86);
        }
        if (!$readable) {
            $confidence = min($confidence, 35);
        }

        if ($hardFail) {
            $result = 'REJECTED';
        } elseif ($strongPass && $confidence >= 80) {
            $result = 'VERIFIED';
        } else {
            $result = 'REVIEW_REQUIRED';
        }

        $failed = [];
        foreach ($checks as $check) {
            if (empty($check['ok'])) {
                $failed[] = $check['message'];
            }
        }

        $summary = match ($result) {
            'VERIFIED' => 'Payment information matches the expected subscription payment.',
            'REJECTED' => $failed ? implode(' ', $failed) : 'The receipt does not satisfy the payment requirements.',
            default => $readable
                ? 'Receipt appears to be a payment receipt, but the system could not confidently verify it automatically.'
                : 'The receipt could not be read with enough confidence and needs manual review.',
        };

        return [
            'result' => $result,
            'confidence' => $confidence,
            'summary' => $summary,
            'checks' => $checks,
            'reasons' => $failed,
        ];
    }
}
