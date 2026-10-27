<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Pdf;

/**
 * Field positions on the two official templates, measured once from their
 * content streams (boxes) and text positions (labels). Units: PDF points,
 * origin top left. Neither template has form fields, so everything is
 * placed by these coordinates.
 *
 * Keys:
 *  - text fields: [x, baseline y, max width]
 *  - blocks (multi-line): [x, top y, width, height]
 *  - checkboxes: [x, y] of the 12.75 pt square
 */
class ReceiptLayout {
    public const MONEY = [
        'template' => '034122.pdf',
        'number' => [553.0, 32.0],
        'issuer' => [48.0, 55.0, 500.0, 36.0],
        'donor' => [48.0, 158.0, 500.0, 37.0],
        'amount_digits' => [48.0, 238.0, 120.0],
        'amount_words' => [178.0, 228.5, 245.0, 17.5],
        'donation_date' => [433.0, 238.0, 115.0],
        'waiver_yes' => [374.23, 269.55],
        'waiver_no' => [433.79, 269.55],
        'exemption_box' => [58.03, 303.90],
        'exemption_purposes' => [82.0, 316.0, 466.0, 44.0],
        'exemption_office' => [84.0, 385.6, 136.0],
        'exemption_tax_number' => [247.0, 385.6, 102.0],
        'exemption_date' => [371.0, 385.6, 58.0],
        'exemption_period' => [166.0, 400.6, 98.0],
        'statute_box' => [58.03, 428.97],
        'statute_office' => [84.0, 453.1, 108.0],
        'statute_tax_number' => [218.0, 453.1, 78.0],
        'statute_date' => [365.0, 453.1, 58.0],
        'statute_purposes' => [82.0, 473.0, 466.0, 56.0],
        'usage_purposes' => [48.0, 547.0, 500.0, 38.0],
        'membership_box' => [46.89, 626.37],
        'sign_line_y' => 691.96,
        'sign_space' => 38.0,
    ];

    public const GOODS = [
        'template' => '034123.pdf',
        'number' => [553.0, 30.0],
        'issuer' => [48.0, 52.0, 500.0, 37.0],
        'donor' => [48.0, 150.0, 500.0, 37.0],
        'amount_digits' => [48.0, 218.0, 118.0],
        'amount_words' => [175.0, 208.5, 245.0, 17.5],
        'donation_date' => [430.0, 218.0, 118.0],
        'goods_description' => [48.0, 243.0, 500.0, 125.0],
        'origin_business' => [57.74, 378.09],
        'origin_private' => [57.74, 395.72],
        'origin_unknown' => [57.74, 411.14],
        'valuation_documents' => [57.74, 425.82],
        'exemption_box' => [57.74, 440.45],
        'exemption_purposes' => [82.0, 452.0, 466.0, 38.0],
        'exemption_office' => [84.0, 513.4, 140.0],
        'exemption_tax_number' => [252.0, 513.4, 106.0],
        'exemption_date' => [382.0, 513.4, 84.0],
        'exemption_period' => [166.0, 527.6, 112.0],
        'statute_box' => [57.74, 548.16],
        'statute_office' => [84.0, 568.7, 139.0],
        'statute_tax_number' => [252.0, 568.7, 124.0],
        'statute_date' => [427.0, 568.7, 68.0],
        'statute_purposes' => [82.0, 586.0, 466.0, 40.0],
        'usage_purposes' => [48.0, 642.0, 500.0, 40.0],
        'sign_line_y' => 714.52,
        'sign_space' => 34.0,
    ];

    public const CHECKBOX_SIZE = 12.75;
}
