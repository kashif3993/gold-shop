<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Reads a photo of a bank transfer slip and looks for a transaction ID on
 * it — free, local OCR (Tesseract), no cloud API, no cost, works offline.
 *
 * Tesseract only extracts raw text; it has no idea what a transaction ID
 * looks like. A slip has several numbers on it (amount, account number,
 * date, reference), so a bare number is never assumed to be the ID —
 * only text that sits next to a recognized label is accepted. Anything
 * else returns null, left for the admin/manager to type in by hand rather
 * than risk saving a wrong value.
 */
class BankSlipOcrService
{
    /** Labels that precede a transaction/reference number on a typical bank slip or app screenshot. */
    private const LABELS = [
        'transaction id', 'txn id', 'trx id', 'tid',
        'reference no', 'reference number', 'ref no', 'ref#', 'ref #',
        'transaction no', 'transaction number',
    ];

    public function detectTransactionId(string $imagePath): ?string
    {
        $text = $this->extractText($imagePath);

        return $text === null ? null : $this->findIdInText($text);
    }

    /**
     * The pure pattern-matching step, split out from the image/OCR call so
     * it can be tested directly against known text without needing the
     * Tesseract binary installed (e.g. in CI).
     */
    public function findIdInText(string $text): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $lower = strtolower($line);

            foreach (self::LABELS as $label) {
                if (! str_contains($lower, $label)) {
                    continue;
                }

                // The code is whatever alphanumeric run sits after the
                // label on the same line (past any ":"/"-" separator).
                $afterLabel = substr($line, strripos($line, $label) + strlen($label));
                if (preg_match('/[A-Za-z0-9]{5,30}/', $afterLabel, $m)) {
                    return $m[0];
                }
            }
        }

        return null;
    }

    private function extractText(string $imagePath): ?string
    {
        try {
            $ocr = new TesseractOCR($imagePath);

            $binaryPath = config('services.tesseract.binary_path');
            if ($binaryPath) {
                $ocr->executable($binaryPath);
            }

            return $ocr->run();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
