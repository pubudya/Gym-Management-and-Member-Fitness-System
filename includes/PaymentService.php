<?php
declare(strict_types=1);

final class PaymentService
{
    public static function record(array $data): int
    {
        $paymentId = Database::insert('payments', [
            'member_id' => $data['member_id'],
            'membership_id' => $data['membership_id'] ?? null,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'] ?? 'PENDING',
            'transaction_reference' => $data['transaction_reference'] ?? ('PAY-' . date('YmdHis') . '-' . random_int(100, 999)),
            'paid_by' => $data['paid_by'] ?? 'ADMIN',
            'payment_date' => $data['payment_date'] ?? date('Y-m-d H:i:s'),
            'notes' => $data['notes'] ?? null,
        ]);

        if (($data['payment_status'] ?? '') === 'SUCCESS' && !empty($data['membership_id'])) {
            self::applyToMembership((int) $data['membership_id'], (float) $data['amount']);
        }

        return $paymentId;
    }

    public static function applyToMembership(int $membershipId, float $amount): void
    {
        $ms = Database::fetch('SELECT * FROM memberships WHERE membership_id = ?', [$membershipId]);
        if (!$ms) {
            return;
        }
        $paid = (float) $ms['paid_amount'] + $amount;
        $balance = max(0, (float) $ms['final_amount'] - $paid);
        $status = $ms['status'];
        if ($status === 'PENDING' && $balance <= 0) {
            $status = 'ACTIVE';
        }
        Database::update('memberships', [
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $status,
        ], 'membership_id = ?', [$membershipId]);
    }

    public static function completeOnline(int $paymentId, bool $success, string $gatewayId = '', string $message = ''): void
    {
        $payment = Database::fetch('SELECT * FROM payments WHERE payment_id = ?', [$paymentId]);
        if (!$payment) {
            return;
        }

        Database::insert('payment_transactions', [
            'payment_id' => $paymentId,
            'gateway_name' => 'GymPay Demo',
            'gateway_transaction_id' => $gatewayId ?: ('GP-' . bin2hex(random_bytes(6))),
            'amount' => $payment['amount'],
            'status' => $success ? 'SUCCESS' : 'FAILED',
            'response_code' => $success ? '00' : '91',
            'response_message' => $message ?: ($success ? 'Approved' : 'Declined'),
        ]);

        Database::update('payments', [
            'payment_status' => $success ? 'SUCCESS' : 'FAILED',
            'payment_date' => date('Y-m-d H:i:s'),
        ], 'payment_id = ?', [$paymentId]);

        $member = Database::fetch('SELECT * FROM members WHERE member_id = ?', [$payment['member_id']]);

        if ($success) {
            if ($payment['membership_id']) {
                self::applyToMembership((int) $payment['membership_id'], (float) $payment['amount']);
            }
            NotificationService::notifyMember(
                $member,
                'PAYMENT_SUCCESS',
                NotificationService::render(
                    'PAYMENT_SUCCESS',
                    ['name' => $member['full_name'] ?? '', 'amount' => money($payment['amount'])],
                    'Your gym payment of ' . money($payment['amount']) . ' was received. Thank you.'
                )
            );
            NotificationService::notifyAdmins(
                'ADMIN_PAYMENT_SUCCESS',
                'Online payment received: ' . ($member['full_name'] ?? 'Member') . ' paid ' . money($payment['amount']) . '.'
            );
        } else {
            NotificationService::notifyMember(
                $member,
                'PAYMENT_FAILED',
                NotificationService::render(
                    'PAYMENT_FAILED',
                    ['name' => $member['full_name'] ?? ''],
                    'Your gym payment could not be completed. Please try again or pay at the desk.'
                )
            );
            NotificationService::notifyAdmins(
                'ADMIN_PAYMENT_FAILED',
                'Online payment failed for ' . ($member['full_name'] ?? 'a member') . ' (' . money($payment['amount']) . ').'
            );
        }

        audit($success ? 'PAYMENT_SUCCESS' : 'PAYMENT_FAILED', 'payments', 'Payment #' . $paymentId, $paymentId);
    }

    public static function receiptNumber(array $payment): string
    {
        return $payment['transaction_reference'] ?: ('RCPT-' . str_pad((string) $payment['payment_id'], 6, '0', STR_PAD_LEFT));
    }
}
