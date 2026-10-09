<?php

namespace Tests\Unit;

use App\Services\BankSlipOcrService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pattern-matching step directly against known OCR text, rather
 * than through a real image — deterministic, and doesn't need the Tesseract
 * binary installed on whatever machine runs the suite (e.g. CI).
 */
class BankSlipOcrServiceTest extends TestCase
{
    private BankSlipOcrService $ocr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ocr = new BankSlipOcrService;
    }

    public function test_finds_an_id_next_to_a_recognized_label(): void
    {
        $text = "Bank Transfer Receipt\nAccount: 1234567890\nAmount: Rs 50,000\nTransaction ID: TXN8847291AB\nStatus: Successful";

        $this->assertSame('TXN8847291AB', $this->ocr->findIdInText($text));
    }

    #[DataProvider('labelProvider')]
    public function test_recognizes_every_supported_label(string $label): void
    {
        $this->assertSame('ABC12345', $this->ocr->findIdInText("{$label}: ABC12345"));
    }

    public static function labelProvider(): array
    {
        return [
            ['Transaction ID'], ['Txn ID'], ['TRX ID'], ['TID'],
            ['Reference No'], ['Reference Number'], ['Ref No'],
            ['Transaction No'], ['Transaction Number'],
        ];
    }

    public function test_returns_null_when_no_recognized_label_is_present(): void
    {
        $text = "Bank Transfer Receipt\nAccount: 1234567890\nAmount: Rs 50,000\nStatus: Successful";

        $this->assertNull($this->ocr->findIdInText($text));
    }

    public function test_does_not_mistake_the_account_number_for_a_transaction_id(): void
    {
        $text = "Account Number: 1234567890\nAmount: Rs 50,000";

        $this->assertNull($this->ocr->findIdInText($text));
    }

    public function test_returns_null_for_empty_text(): void
    {
        $this->assertNull($this->ocr->findIdInText(''));
    }
}
