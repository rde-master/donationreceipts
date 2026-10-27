<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Pdf;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Service\AmountInWords;
use OCA\DonationReceipts\Service\GermanFormat;
use OCA\DonationReceipts\Service\SettingsService;
use setasign\Fpdi\Fpdi;

/**
 * Fills the official template (034122 money / 034123 goods) for one receipt.
 * Text is set at the measured positions (ReceiptLayout) in Helvetica, which
 * matches the Arial of the templates.
 */
class ReceiptPdf {
    private const FONT = 'Helvetica';
    private const SIZE = 10.0;
    private const MIN_SIZE = 6.5;

    private Fpdi $pdf;
    /** @var array<string, mixed> */
    private array $layout;

    /**
     * @param array<string, string|bool|int> $settings SettingsService::getAll()
     * @param string|null $signaturePath PNG of the signature; null = unsigned version
     * @param string|null $stampPath PNG of the stamp; null = unsigned version
     */
    public function render(Receipt $receipt, Donor $donor, array $settings, ?string $signaturePath = null, ?string $stampPath = null): string {
        $this->layout = $receipt->getType() === Receipt::TYPE_GOODS ? ReceiptLayout::GOODS : ReceiptLayout::MONEY;

        $this->pdf = new Fpdi('P', 'pt', 'A4');
        $this->pdf->SetCreator('Nextcloud Spendenbescheinigungen');
        $this->pdf->SetTitle($this->encode('Zuwendungsbestätigung ' . $receipt->getNumber()));
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->AddPage();
        $this->pdf->setSourceFile(dirname(__DIR__, 2) . '/resources/' . $this->layout['template']);
        $this->pdf->useTemplate($this->pdf->importPage(1), 0, 0, null, null, true);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetDrawColor(0, 0, 0);

        $this->numberMark($receipt->getNumber());
        $this->block('issuer', [
            (string)$settings['org_name'],
            (string)$settings['org_street'],
            trim($settings['org_zip'] . ' ' . $settings['org_city']),
        ]);
        $this->block('donor', $donor->getAddressLines());

        $this->text('amount_digits', GermanFormat::amount($receipt->getAmountCents()) . ' €');
        $this->block('amount_words', [AmountInWords::euro($receipt->getAmountCents())]);
        $this->text('donation_date', GermanFormat::date($receipt->getDonationDate()));

        if ($receipt->getType() === Receipt::TYPE_GOODS) {
            $this->block('goods_description', [(string)$receipt->getGoodsDescription()]);
            $origin = match ($receipt->getGoodsOrigin()) {
                Receipt::ORIGIN_BUSINESS => 'origin_business',
                Receipt::ORIGIN_PRIVATE => 'origin_private',
                default => 'origin_unknown',
            };
            $this->cross($origin);
            if ($receipt->getGoodsValuationDocuments()) {
                $this->cross('valuation_documents');
            }
        } else {
            $this->cross($receipt->getExpenseWaiver() ? 'waiver_yes' : 'waiver_no');
            if ($settings['membership_fees_not_deductible']) {
                $this->cross('membership_box');
            }
        }

        $purposes = (string)$settings['purposes'];
        if ($settings['tax_mode'] === SettingsService::TAX_MODE_STATUTE) {
            $this->cross('statute_box');
            $this->text('statute_office', (string)$settings['tax_office']);
            $this->text('statute_tax_number', (string)$settings['tax_number']);
            $this->text('statute_date', GermanFormat::date((string)$settings['statute_date']));
            $this->block('statute_purposes', [$purposes]);
        } else {
            $this->cross('exemption_box');
            $this->block('exemption_purposes', [$purposes]);
            $this->text('exemption_office', (string)$settings['tax_office']);
            $this->text('exemption_tax_number', (string)$settings['tax_number']);
            $this->text('exemption_date', GermanFormat::date((string)$settings['exemption_date']));
            $this->text('exemption_period', (string)$settings['exemption_period']);
        }
        $this->block('usage_purposes', [$purposes]);

        $this->signatureArea($receipt, $settings, $signaturePath, $stampPath);

        return $this->pdf->Output('S');
    }

    /** Receipt number, small, top right above the issuer box. */
    private function numberMark(string $number): void {
        [$right, $y] = $this->layout['number'];
        $this->pdf->SetFont(self::FONT, '', 8);
        $text = $this->encode('Nr. ' . $number);
        $this->pdf->Text($right - $this->pdf->GetStringWidth($text), $y, $text);
    }

    /**
     * Above the signature line: "Ort, Datum" on the left; in the signed
     * version signature and stamp; the signer's name and function small on
     * the right.
     *
     * @param array<string, string|bool|int> $settings
     */
    private function signatureArea(Receipt $receipt, array $settings, ?string $signaturePath, ?string $stampPath): void {
        $lineY = $this->layout['sign_line_y'];
        $space = $this->layout['sign_space'];

        $placeDate = trim((string)$settings['sign_place']);
        $placeDate = ($placeDate !== '' ? $placeDate . ', ' : '') . GermanFormat::date($receipt->getIssueDate());
        $this->fitText(48.0, $lineY - 4, 140.0, $placeDate, self::SIZE);

        $signer = trim(implode(', ', array_filter([
            trim((string)$settings['signer_name']),
            trim((string)$settings['signer_function']),
        ])));
        if ($signer !== '') {
            $this->pdf->SetFont(self::FONT, '', 7);
            $text = $this->fitEncoded($signer, 170.0, 7.0);
            $this->pdf->Text(550.0 - $this->pdf->GetStringWidth($text), $lineY - 3, $text);
        }

        if ($signaturePath !== null) {
            $this->image($signaturePath, 195.0, $lineY - 1, 150.0, $space);
        }
        if ($stampPath !== null) {
            // The stamp may reach a little higher than the free space, as on paper.
            $this->image($stampPath, 355.0, $lineY + 4, 95.0, $space + 18);
        }
    }

    /** Places an image inside max width/height, bottom-left anchored. */
    private function image(string $path, float $x, float $bottom, float $maxW, float $maxH): void {
        $size = getimagesize($path);
        if ($size === false || $size[0] === 0 || $size[1] === 0) {
            throw new \RuntimeException('Bilddatei kann nicht gelesen werden');
        }
        $scale = min($maxW / $size[0], $maxH / $size[1]);
        $w = $size[0] * $scale;
        $h = $size[1] * $scale;
        $this->pdf->Image($path, $x, $bottom - $h, $w, $h, 'PNG');
    }

    private function cross(string $key): void {
        [$x, $y] = $this->layout[$key];
        $s = ReceiptLayout::CHECKBOX_SIZE;
        $inset = 2.6;
        $this->pdf->SetLineWidth(1.1);
        $this->pdf->Line($x + $inset, $y + $inset, $x + $s - $inset, $y + $s - $inset);
        $this->pdf->Line($x + $inset, $y + $s - $inset, $x + $s - $inset, $y + $inset);
    }

    /** Single-line field: shrinks the font until the text fits. */
    private function text(string $key, string $text): void {
        [$x, $y, $w] = $this->layout[$key];
        $this->fitText($x, $y, $w, $text, self::SIZE);
    }

    private function fitText(float $x, float $baseline, float $width, string $text, float $size): void {
        $encoded = $this->fitEncoded($text, $width, $size);
        $this->pdf->Text($x, $baseline, $encoded);
    }

    /** Sets the largest font size <= $size at which $text fits; returns it encoded. */
    private function fitEncoded(string $text, float $width, float $size): string {
        $encoded = $this->encode($text);
        for (; $size > self::MIN_SIZE; $size -= 0.5) {
            $this->pdf->SetFont(self::FONT, '', $size);
            if ($this->pdf->GetStringWidth($encoded) <= $width) {
                return $encoded;
            }
        }
        $this->pdf->SetFont(self::FONT, '', self::MIN_SIZE);
        return $encoded;
    }

    /**
     * Multi-line area: each paragraph is word-wrapped; the font shrinks until
     * all lines fit into the area's height.
     *
     * @param list<string> $paragraphs
     */
    private function block(string $key, array $paragraphs): void {
        [$x, $top, $w, $h] = $this->layout[$key];
        $paragraphs = array_values(array_filter(
            array_merge(...array_map(static fn (string $p) => preg_split('/\R/', $p), $paragraphs)),
            static fn (string $p) => trim($p) !== '',
        ));
        if ($paragraphs === []) {
            return;
        }

        for ($size = self::SIZE; ; $size -= 0.5) {
            $this->pdf->SetFont(self::FONT, '', $size);
            $leading = $size * 1.18;
            $lines = [];
            foreach ($paragraphs as $paragraph) {
                array_push($lines, ...$this->wrap($this->encode(trim($paragraph)), $w));
            }
            if (count($lines) * $leading <= $h || $size <= self::MIN_SIZE) {
                break;
            }
        }

        $y = $top + $size;
        foreach ($lines as $line) {
            if ($y > $top + $h + 1) {
                break;
            }
            $this->pdf->Text($x, $y, $line);
            $y += $leading;
        }
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, float $width): array {
        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && $this->pdf->GetStringWidth($candidate) > $width) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }

    /** FPDF core fonts use Windows-1252 (covers German umlauts and €). */
    private function encode(string $text): string {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        return $converted === false ? mb_convert_encoding($text, 'Windows-1252', 'UTF-8') : $converted;
    }
}
