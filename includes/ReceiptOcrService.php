<?php
declare(strict_types=1);

final class ReceiptOcrService
{
    public static function analyze(string $absolutePath, string $mime): array
    {
        $text = '';
        $engine = 'none';

        if (!is_file($absolutePath)) {
            return self::result('', 'missing', false);
        }

        if ($mime === 'application/pdf') {
            $extracted = self::pdfText($absolutePath);
            if ($extracted['text'] !== '') {
                return self::result($extracted['text'], $extracted['engine'], true);
            }
        }

        $ocrPath = $absolutePath;
        $temp = self::preprocessImage($absolutePath, $mime);
        if ($temp) {
            $ocrPath = $temp;
        }

        $tess = self::tesseract($ocrPath);
        if ($tess['text'] !== '') {
            $text = $tess['text'];
            $engine = $tess['engine'];
        }

        if ($temp && is_file($temp)) {
            @unlink($temp);
        }

        $readable = self::isReadable($text);
        return self::result($text, $engine, $readable);
    }

    private static function result(string $text, string $engine, bool $readable): array
    {
        $normalized = self::normalize($text);
        return [
            'text' => $normalized,
            'engine' => $engine,
            'readable' => $readable && $normalized !== '',
            'parsed' => self::parse($normalized),
        ];
    }

    public static function parse(string $text): array
    {
        return [
            'amount' => self::extractAmount($text),
            'date' => self::extractDate($text),
            'reference' => self::extractReference($text),
            'bank' => self::extractBank($text),
            'sender' => self::extractLabeled($text, ['from', 'sender', 'payer', 'remitter', 'account holder', 'debited from']),
            'recipient' => self::extractLabeled($text, ['to', 'beneficiary', 'recipient', 'payee', 'credited to', 'account name']),
            'looks_like_receipt' => self::looksLikeReceipt($text),
        ];
    }

    public static function looksLikeReceipt(string $text): bool
    {
        $hay = strtolower($text);
        $hits = 0;
        foreach ([
            'paid', 'payment', 'receipt', 'transfer', 'deposit', 'transaction',
            'amount', 'lkr', 'rs.', 'rs ', 'bank', 'slip', 'reference', 'txn',
            'ceft', 'slips', 'fund transfer', 'successful', 'completed',
        ] as $word) {
            if (str_contains($hay, $word)) {
                $hits++;
            }
        }
        return $hits >= 2;
    }

    private static function extractAmount(string $text): ?float
    {
        $candidates = [];
        $patterns = [
            '/(?:lkr|rs\.?|amount|paid|total|lkr\s*)[^\d]{0,12}(\d{1,3}(?:[,\s]\d{3})+(?:\.\d{2})?|\d+(?:\.\d{2})?)/i',
            '/(\d{1,3}(?:,\d{3})+(?:\.\d{2})?)/',
            '/\b(10000(?:\.00)?)(?:\b)/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $m)) {
                foreach ($m[1] as $raw) {
                    $value = (float) str_replace([',', ' '], '', $raw);
                    if ($value >= 100 && $value <= 1000000) {
                        $candidates[] = $value;
                    }
                }
            }
        }
        if (!$candidates) {
            return null;
        }
        $counts = array_count_values(array_map(static fn ($v) => (string) $v, $candidates));
        arsort($counts);
        return (float) array_key_first($counts);
    }

    private static function extractDate(string $text): ?string
    {
        $patterns = [
            '/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/',
            '/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/',
            '/\b(\d{1,2})\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+(\d{4})\b/i',
        ];
        if (preg_match($patterns[0], $text, $m)) {
            return self::isoDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match($patterns[1], $text, $m)) {
            return self::isoDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match($patterns[2], $text, $m)) {
            $month = date('n', strtotime('1 ' . $m[2] . ' 2000'));
            return self::isoDate((int) $m[3], (int) $month, (int) $m[1]);
        }
        return null;
    }

    private static function isoDate(int $year, int $month, int $day): ?string
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function extractReference(string $text): ?string
    {
        $patterns = [
            '/(?:txn|transaction|trace|reference|ref(?:erence)?(?:\s*no\.?)?|rrn|stan|seq(?:uence)?)\s*[:#-]?\s*([A-Z0-9\-\/]{6,24})/i',
            '/\b((?:TXN|TRX|REF|RRN)[A-Z0-9\-]{4,20})\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return strtoupper(trim($m[1]));
            }
        }
        return null;
    }

    private static function extractBank(string $text): ?string
    {
        $banks = [
            'Commercial Bank' => ['commercial bank', 'combank', 'cbc'],
            'Bank of Ceylon' => ['bank of ceylon', 'boc'],
            'People\'s Bank' => ['people\'s bank', 'peoples bank'],
            'Hatton National Bank' => ['hatton national', 'hnb'],
            'Sampath Bank' => ['sampath'],
            'Nations Trust Bank' => ['nations trust', 'ntb'],
            'DFCC Bank' => ['dfcc'],
            'NSB' => ['national savings', 'nsb'],
            'Seylan Bank' => ['seylan'],
        ];
        $hay = strtolower($text);
        foreach ($banks as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($hay, $needle)) {
                    return $name;
                }
            }
        }
        return self::extractLabeled($text, ['bank', 'bank name']);
    }

    private static function extractLabeled(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            if (preg_match('/' . $quoted . '\s*[:\-]\s*([A-Za-z0-9 .,&\'-]{3,80})/i', $text, $m)) {
                $value = trim($m[1]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private static function normalize(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function isReadable(string $text): bool
    {
        $letters = preg_match_all('/[A-Za-z]/', $text);
        return $letters >= 12 || (strlen(trim($text)) >= 20 && $letters >= 6);
    }

    private static function tesseract(string $path): array
    {
        $bin = self::tesseractBinary();
        if (!$bin) {
            return ['text' => '', 'engine' => 'none'];
        }
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' stdout -l eng --psm 6 2>NUL';
        $out = self::run($cmd);
        if (trim($out) !== '') {
            return ['text' => $out, 'engine' => 'tesseract'];
        }
        return ['text' => '', 'engine' => 'tesseract-empty'];
    }

    private static function tesseractBinary(): ?string
    {
        $candidates = [
            'tesseract',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        ];
        foreach ($candidates as $bin) {
            if ($bin !== 'tesseract' && !is_file($bin)) {
                continue;
            }
            $probe = $bin === 'tesseract'
                ? 'tesseract --version 2>NUL'
                : escapeshellarg($bin) . ' --version 2>NUL';
            $out = self::run($probe);
            if (stripos($out, 'tesseract') !== false) {
                return $bin;
            }
        }
        return null;
    }

    private static function pdfText(string $path): array
    {
        $binaries = ['pdftotext'];
        foreach ($binaries as $bin) {
            $out = self::run(escapeshellarg($bin) . ' -raw ' . escapeshellarg($path) . ' - 2>NUL');
            if (trim($out) !== '') {
                return ['text' => $out, 'engine' => 'pdftotext'];
            }
        }
        return ['text' => '', 'engine' => 'pdf-none'];
    }

    private static function preprocessImage(string $path, string $mime): ?string
    {
        if (!extension_loaded('gd') || !str_starts_with($mime, 'image/')) {
            return null;
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
        if (!$src) {
            return null;
        }
        imagefilter($src, IMG_FILTER_GRAYSCALE);
        imagefilter($src, IMG_FILTER_CONTRAST, -20);
        $temp = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'volt-ocr-' . bin2hex(random_bytes(6)) . '.png';
        $ok = imagepng($src, $temp);
        imagedestroy($src);
        return $ok ? $temp : null;
    }

    private static function run(string $command): string
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return '';
        }
        $lines = [];
        $code = 0;
        @exec($command, $lines, $code);
        return implode("\n", $lines);
    }
}
