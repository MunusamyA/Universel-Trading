<?php
/*
 * S.M TRADERS Tax Invoice - dynamic FPDF style with automatic no-overlap currency fitting.
 * Changes in this version:
 * 1) Keeps the compact Indian Rupee symbol method for FPDF without broken glyphs.
 * 2) Keeps Indian currency formatting without visible space after the rupee symbol.
 * 3) Adds formal right-side padding for amount values so they do not touch table borders.
 * 4) Automatically wraps long GST/discount currency values inside the same table cell to avoid overlap.
 * 5) No CSS, JavaScript, or HTML output.
 */

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!class_exists('FPDF')) {
    $fpdfCandidates = array(
        __DIR__ . '/fpdf.php',
        __DIR__ . '/fpdf/fpdf.php',
        __DIR__ . '/libs/fpdf.php',
        __DIR__ . '/libs/fpdf/fpdf.php',
        __DIR__ . '/assets/libs/fpdf/fpdf.php',
        dirname(__DIR__) . '/libs/fpdf.php',
        dirname(__DIR__) . '/libs/fpdf/fpdf.php'
    );

    foreach ($fpdfCandidates as $fpdfFile) {
        if (is_file($fpdfFile)) {
            require_once $fpdfFile;
            break;
        }
    }
}

if (!class_exists('FPDF')) {
    exit('FPDF library not found. Please install FPDF or keep vendor/autoload.php available.');
}

class ExactSMTInvoicePDF extends FPDF
{
    public $fontFamily = 'Arial';

    public function clean($text)
    {
        $text = (string)$text;
        $text = preg_replace('/<[^>]*>/', '', $text);
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $text = str_replace(
            array('“', '”', '‘', '’', '–', '—', '…', '&nbsp;'),
            array('"', '"', "'", "'", '-', '-', '...', ' '),
            $text
        );
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
        return ($converted !== false) ? $converted : $text;
    }

    public function setF($style, $size)
    {
        $this->SetFont($this->fontFamily, $style, $size);
    }

    public function fitText($x, $y, $w, $h, $text, $align = 'L', $style = '', $size = 8.5, $min = 5.5)
    {
        $text = $this->clean($text);
        $s = $size;
        while ($s > $min) {
            $this->setF($style, $s);
            if ($this->GetStringWidth($text) <= ($w - 2)) {
                break;
            }
            $s -= 0.25;
        }
        $this->SetXY($x, $y);
        $this->Cell($w, $h, $text, 0, 0, $align);
    }

    public function wrapLines($text, $w, $style = '', $size = 8.0)
    {
        $text = $this->clean($text);
        $this->setF($style, $size);
        $maxW = max(1, $w - 1.0);
        $result = array();
        $paragraphs = explode("\n", $text);

        foreach ($paragraphs as $para) {
            $words = preg_split('/\s+/', trim($para));
            if (!$words || $words[0] === '') {
                $result[] = '';
                continue;
            }

            $line = '';
            foreach ($words as $word) {
                $try = ($line === '') ? $word : ($line . ' ' . $word);
                if ($this->GetStringWidth($try) <= $maxW) {
                    $line = $try;
                } else {
                    if ($line !== '') {
                        $result[] = $line;
                    }
                    $line = $word;
                }
            }
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    public function blockText($x, $y, $w, $h, $text, $align = 'L', $style = '', $size = 8.0, $min = 6.2, $lineH = null)
    {
        $s = $size;
        $lines = array();
        $lh = $lineH ? $lineH : ($s + 2.2);

        while ($s >= $min) {
            $lh = $lineH ? $lineH : ($s + 2.2);
            $lines = $this->wrapLines($text, $w, $style, $s);
            if ((count($lines) * $lh) <= ($h - 2.0)) {
                break;
            }
            $s -= 0.25;
        }

        $this->setF($style, $s);
        $lh = $lineH ? $lineH : ($s + 2.2);
        $totalH = count($lines) * $lh;
        $startY = $y + max(1.0, (($h - $totalH) / 2));

        foreach ($lines as $line) {
            $this->SetXY($x, $startY);
            $this->Cell($w, $lh, $line, 0, 0, $align);
            $startY += $lh;
        }
    }

    public function drawRupee($x, $y, $s = 5.7)
    {
        // Darker vector Indian Rupee sign. This avoids FPDF/Arial Unicode glyph problems.
        $oldWidth = $this->LineWidth;
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(max(0.36, $s * 0.090));
        $this->Line($x + $s * 0.08, $y + $s * 0.15, $x + $s * 0.86, $y + $s * 0.15);
        $this->Line($x + $s * 0.10, $y + $s * 0.34, $x + $s * 0.74, $y + $s * 0.34);
        $this->Line($x + $s * 0.31, $y + $s * 0.15, $x + $s * 0.31, $y + $s * 0.56);
        $this->Line($x + $s * 0.31, $y + $s * 0.56, $x + $s * 0.80, $y + $s * 0.92);
        $this->SetLineWidth($oldWidth);
    }

    public function moneyNumber($value)
    {
        // Indian currency number format: 12,34,567.89
        $value = (float)$value;
        $negative = $value < 0;
        $value = abs($value);

        $formatted = number_format($value, 2, '.', '');
        $parts = explode('.', $formatted);
        $integer = $parts[0];
        $decimal = isset($parts[1]) ? $parts[1] : '00';

        if (strlen($integer) > 3) {
            $lastThree = substr($integer, -3);
            $remaining = substr($integer, 0, -3);
            $remaining = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining);
            $integer = $remaining . ',' . $lastThree;
        }

        return ($negative ? '-' : '') . $integer . '.' . $decimal;
    }

    public function money($x, $y, $w, $h, $value, $align = 'R', $style = '', $size = 8.0, $symbolSize = null)
    {
        $num = $this->moneyNumber($value);
        $s = $size;
        $sym = ($symbolSize === null) ? ($s * 0.82) : (float)$symbolSize;

        // No visible space between ₹ and amount; keep it like ₹50.00.
        $gap = -0.35;
        // Formal right padding keeps currency values away from table borders.
        $pad = 5.2;
        $symbolW = $sym * 0.68;

        while ($s > 4.2) {
            $this->setF($style, $s);
            $textW = $this->GetStringWidth($num);
            $symbolW = $sym * 0.68;
            $fullW = $symbolW + $gap + $textW;
            if ($fullW <= ($w - ($pad * 2))) {
                break;
            }
            $s -= 0.2;
            $sym = $s * 0.82;
        }

        $this->setF($style, $s);
        $textW = $this->GetStringWidth($num);
        $symbolW = $sym * 0.68;
        $fullW = $symbolW + $gap + $textW;

        if ($align === 'C') {
            $sx = $x + (($w - $fullW) / 2);
        } elseif ($align === 'L') {
            $sx = $x + $pad;
        } else {
            $sx = $x + $w - $fullW - $pad;
        }

        // Guard against ultra-long values touching the left border after font fitting.
        if ($sx < ($x + 1.2)) {
            $sx = $x + 1.2;
        }

        $sy = $y + (($h - $sym) / 2) + 0.42;
        $this->drawRupee($sx, $sy, $sym);
        $this->SetXY($sx + $symbolW + $gap, $y);
        $this->Cell(max(1, $w - ($sx - $x) - $symbolW), $h, $num, 0, 0, 'L');
    }

    public function moneyPercent($x, $y, $w, $h, $amount, $rate, $align = 'R', $style = '', $size = 7.6)
    {
        $rateText = '(' . $this->percent($rate) . '%)';
        $amountText = $this->moneyNumber($amount);
        $fullText = $amountText . ' ' . $rateText;
        $s = $size;
        $sym = $s * 0.80;

        // No visible space between ₹ and the amount; keep it like ₹46.08 (8%).
        $gap = -0.35;
        // Formal right padding keeps currency values away from table borders.
        $pad = 3.8;
        $symbolW = $sym * 0.68;
        $fitsSingleLine = false;

        while ($s > 4.8) {
            $this->setF($style, $s);
            $textW = $this->GetStringWidth($fullText);
            $symbolW = $sym * 0.68;
            $fullW = $symbolW + $gap + $textW;
            if ($fullW <= ($w - ($pad * 2))) {
                $fitsSingleLine = true;
                break;
            }
            $s -= 0.2;
            $sym = $s * 0.80;
        }

        if ($fitsSingleLine) {
            $this->setF($style, $s);
            $textW = $this->GetStringWidth($fullText);
            $symbolW = $sym * 0.68;
            $fullW = $symbolW + $gap + $textW;

            if ($align === 'C') {
                $sx = $x + (($w - $fullW) / 2);
            } elseif ($align === 'L') {
                $sx = $x + $pad;
            } else {
                $sx = $x + $w - $fullW - $pad;
            }

            if ($sx < ($x + 1.0)) {
                $sx = $x + 1.0;
            }

            $sy = $y + (($h - $sym) / 2) + 0.42;
            $this->drawRupee($sx, $sy, $sym);
            $this->SetXY($sx + $symbolW + $gap, $y);
            $this->Cell(max(1, $w - ($sx - $x) - $symbolW), $h, $fullText, 0, 0, 'L');
            return;
        }

        // Long GST/discount values are automatically moved to two lines inside the cell.
        // Example:
        //   ₹58,88,888.45
        //   (18%)
        $s = min($size, 6.4);
        $sym = $s * 0.80;
        $pad = 3.0;
        while ($s > 4.2) {
            $this->setF($style, $s);
            $textW = $this->GetStringWidth($amountText);
            $symbolW = $sym * 0.68;
            $fullW = $symbolW + $gap + $textW;
            if ($fullW <= ($w - ($pad * 2))) {
                break;
            }
            $s -= 0.2;
            $sym = $s * 0.80;
        }

        $this->setF($style, $s);
        $textW = $this->GetStringWidth($amountText);
        $symbolW = $sym * 0.68;
        $fullW = $symbolW + $gap + $textW;
        if ($align === 'C') {
            $sx = $x + (($w - $fullW) / 2);
        } elseif ($align === 'L') {
            $sx = $x + $pad;
        } else {
            $sx = $x + $w - $fullW - $pad;
        }
        if ($sx < ($x + 1.0)) {
            $sx = $x + 1.0;
        }

        $lineH = max(6.2, min(8.2, $h / 2));
        $topY = $y + max(0.0, ($h - ($lineH * 2)) / 2);
        $sy = $topY + (($lineH - $sym) / 2) + 0.42;
        $this->drawRupee($sx, $sy, $sym);
        $this->SetXY($sx + $symbolW + $gap, $topY);
        $this->Cell(max(1, $w - ($sx - $x) - $symbolW), $lineH, $amountText, 0, 0, 'L');

        $rateSize = max(4.8, min(6.2, $s));
        $this->setF($style, $rateSize);
        $this->SetXY($x + 1.0, $topY + $lineH);
        $this->Cell($w - 2.0, $lineH, $rateText, 0, 0, $align);
    }

    public function percent($value)
    {
        $value = (float)$value;
        if (floor($value) == $value) {
            return number_format($value, 0, '.', '');
        }
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}


/* ---------------------------------------------------------
 * Dynamic data mapping
 * Compatible with invoice_print.php design variables:
 * $invoice, $settings / $invoice_settings, $invoiceItems / $invoice_items / $items,
 * $bank_accounts / $banks / $accounts.
 * --------------------------------------------------------- */
if (!function_exists('smt_pick')) {
    function smt_pick($row, $keys, $default = '')
    {
        if (!is_array($row)) {
            return $default;
        }
        foreach ((array)$keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('smt_float')) {
    function smt_float($value, $default = 0.0)
    {
        if ($value === null || $value === '') {
            return (float)$default;
        }
        return (float)str_replace(array(',', '₹', 'Rs.', 'Rs'), '', (string)$value);
    }
}

if (!function_exists('smt_qty_text')) {
    function smt_qty_text($value)
    {
        $value = (float)$value;
        return (floor($value) == $value) ? (string)(int)$value : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('smt_date_text')) {
    function smt_date_text($value, $default = '')
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00') {
            return $default;
        }
        $time = strtotime($value);
        return $time ? date('d-m-Y', $time) : $value;
    }
}

if (!function_exists('smt_state_text')) {
    function smt_state_text($state, $default = '33-Tamil Nadu')
    {
        $state = trim((string)$state);
        if ($state === '') {
            return $default;
        }
        if (stripos($state, 'tamil') !== false && strpos($state, '33') === false) {
            return '33-Tamil Nadu';
        }
        return $state;
    }
}

if (!function_exists('smt_number_words')) {
    function smt_number_words($number)
    {
        $number = (int)$number;
        $words = array(
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty',
            50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        );
        if ($number == 0) {
            return 'Zero';
        }
        if ($number < 0) {
            return 'Minus ' . smt_number_words(abs($number));
        }
        if ($number < 21) {
            return $words[$number];
        }
        if ($number < 100) {
            $tens = ((int)floor($number / 10)) * 10;
            $unit = $number % 10;
            return $words[$tens] . ($unit ? ' ' . $words[$unit] : '');
        }
        if ($number < 1000) {
            $hundreds = (int)floor($number / 100);
            $rem = $number % 100;
            return $words[$hundreds] . ' Hundred' . ($rem ? ' ' . smt_number_words($rem) : '');
        }
        if ($number < 100000) {
            $thousands = (int)floor($number / 1000);
            $rem = $number % 1000;
            return smt_number_words($thousands) . ' Thousand' . ($rem ? ' ' . smt_number_words($rem) : '');
        }
        if ($number < 10000000) {
            $lakhs = (int)floor($number / 100000);
            $rem = $number % 100000;
            return smt_number_words($lakhs) . ' Lakh' . ($rem ? ' ' . smt_number_words($rem) : '');
        }
        $crores = (int)floor($number / 10000000);
        $rem = $number % 10000000;
        return smt_number_words($crores) . ' Crore' . ($rem ? ' ' . smt_number_words($rem) : '');
    }
}

$invoiceData = (isset($invoice) && is_array($invoice)) ? $invoice : array();
$settingsData = array();
if (isset($settings) && is_array($settings)) {
    $settingsData = $settings;
} elseif (isset($invoice_settings) && is_array($invoice_settings)) {
    $settingsData = $invoice_settings;
}

$sourceItems = array();
if (isset($invoiceItems) && is_array($invoiceItems)) {
    $sourceItems = $invoiceItems;
} elseif (isset($invoice_items) && is_array($invoice_items)) {
    $sourceItems = $invoice_items;
} elseif (isset($items) && is_array($items)) {
    $sourceItems = $items;
}

$bankRows = array();
if (isset($bank_accounts) && is_array($bank_accounts)) {
    $bankRows = $bank_accounts;
} elseif (isset($banks) && is_array($banks)) {
    $bankRows = $banks;
} elseif (isset($accounts) && is_array($accounts)) {
    $bankRows = $accounts;
}

// Standalone preview fallback. Dynamic invoice_print.php will override all of these values.
if (empty($invoiceData)) {
    $invoiceData = array(
        'invoice_number' => 'SMT/26-27/0837',
        'created_at' => '2026-06-22',
        'customer_name' => 'GOMATHI MEDICAL',
        'customer_phone' => '9361763199',
        'customer_address' => "B.Agraharam\npennagaram main road",
        'customer_state' => '33-Tamil Nadu',
        'total' => 7444.00
    );
}

if (empty($settingsData)) {
    $settingsData = array(
        'company_name' => 'S.M TRADERS',
        'company_address' => 'Nagadhasampatti ,pennagaram (T.K),Dharmapuri (D.T)',
        'company_phone' => '8428002727',
        'gst_number' => '33DIGPA2921H1ZS',
        'state' => '33-Tamil Nadu',
        'invoice_terms' => 'Thanks for doing business with us!'
    );
}

if (empty($sourceItems)) {
    $sourceItems = array(
        array('name'=>'SF NIGHT 50', 'hsn'=>'96190010', 'mrp'=>50.00, 'qty'=>48, 'unit'=>'PCS', 'price'=>40.00, 'disc'=>0.00, 'disc_rate'=>0, 'gst'=>0.00, 'gst_rate'=>0, 'amount'=>1920.00),
        array('name'=>"HIM BABY GENTLE SOAP\n75GM", 'hsn'=>'11110', 'mrp'=>62.00, 'qty'=>12, 'unit'=>'PCS', 'price'=>48.00, 'disc'=>46.08, 'disc_rate'=>8, 'gst'=>26.50, 'gst_rate'=>5, 'amount'=>556.42),
        array('name'=>"BABY POWDER 100GM RS-\n120", 'hsn'=>'33049120', 'mrp'=>120.00, 'qty'=>12, 'unit'=>'PCS', 'price'=>92.91, 'disc'=>89.20, 'disc_rate'=>8, 'gst'=>51.29, 'gst_rate'=>5, 'amount'=>1077.06),
        array('name'=>'HIM ORANGE FW 50ML', 'hsn'=>'33049990', 'mrp'=>115.00, 'qty'=>3, 'unit'=>'PCS', 'price'=>88.59, 'disc'=>13.29, 'disc_rate'=>5, 'gst'=>45.45, 'gst_rate'=>18, 'amount'=>297.94),
        array('name'=>'HIM LEMON FW 50ML', 'hsn'=>'9990', 'mrp'=>95.00, 'qty'=>3, 'unit'=>'PCS', 'price'=>73.19, 'disc'=>10.98, 'disc_rate'=>5, 'gst'=>37.54, 'gst_rate'=>18, 'amount'=>246.13),
        array('name'=>'HIM ALOEVERA FW100ML', 'hsn'=>'9990', 'mrp'=>189.00, 'qty'=>2, 'unit'=>'PCS', 'price'=>145.60, 'disc'=>14.56, 'disc_rate'=>5, 'gst'=>49.80, 'gst_rate'=>18, 'amount'=>326.44),
        array('name'=>"HIM BABY WIPES 12'S", 'hsn'=>'9090', 'mrp'=>48.00, 'qty'=>3, 'unit'=>'NOS', 'price'=>33.07, 'disc'=>4.96, 'disc_rate'=>5, 'gst'=>16.96, 'gst_rate'=>18, 'amount'=>111.21),
        array('name'=>'MOOV SPARY 35GM RS-186', 'hsn'=>'30049011', 'mrp'=>186.00, 'qty'=>6, 'unit'=>'PCS', 'price'=>154.03, 'disc'=>0.00, 'disc_rate'=>0, 'gst'=>46.21, 'gst_rate'=>5, 'amount'=>970.38),
        array('name'=>'MOOV CREAM 10G RS 68', 'hsn'=>'9011', 'mrp'=>68.00, 'qty'=>12, 'unit'=>'PCS', 'price'=>53.96, 'disc'=>0.00, 'disc_rate'=>0, 'gst'=>32.38, 'gst_rate'=>5, 'amount'=>679.92),
        array('name'=>'HIM GIFT BOX 7s', 'hsn'=>'9990', 'mrp'=>670.00, 'qty'=>1, 'unit'=>'PCS', 'price'=>461.62, 'disc'=>27.70, 'disc_rate'=>6, 'gst'=>78.11, 'gst_rate'=>18, 'amount'=>512.03),
        array('name'=>'HIM GIFT BOX 5,S', 'hsn'=>'', 'mrp'=>476.00, 'qty'=>1, 'unit'=>'PCS', 'price'=>327.97, 'disc'=>19.68, 'disc_rate'=>6, 'gst'=>55.49, 'gst_rate'=>18, 'amount'=>363.78),
        array('name'=>'HIM GIFT BOX RS-501', 'hsn'=>'', 'mrp'=>501.00, 'qty'=>1, 'unit'=>'PCS', 'price'=>345.34, 'disc'=>20.72, 'disc_rate'=>6, 'gst'=>58.43, 'gst_rate'=>18, 'amount'=>383.05)
    );
}

$companyName = trim((string)smt_pick($settingsData, array('company_name', 'shop_name', 'business_name'), smt_pick($invoiceData, array('shop_name'), 'S.M TRADERS')));
$companyAddress = trim((string)smt_pick($settingsData, array('company_address', 'address'), smt_pick($invoiceData, array('shop_address'), 'Nagadhasampatti ,pennagaram (T.K),Dharmapuri (D.T)')));
$companyPhone = trim((string)smt_pick($settingsData, array('company_phone', 'phone'), smt_pick($invoiceData, array('shop_phone'), '8428002727')));
$companyGstin = trim((string)smt_pick($settingsData, array('gst_number', 'gstin', 'company_gstin'), smt_pick($invoiceData, array('shop_gstin'), '33DIGPA2921H1ZS')));
$companyState = smt_state_text(smt_pick($settingsData, array('state', 'company_state'), '33-Tamil Nadu'));

$customerName = trim((string)smt_pick($invoiceData, array('customer_name', 'billing_name', 'name'), 'GOMATHI MEDICAL'));
$customerPhone = trim((string)smt_pick($invoiceData, array('customer_phone', 'phone', 'mobile', 'billing_phone'), '9361763199'));
$customerAddress = trim((string)smt_pick($invoiceData, array('customer_address', 'billing_address', 'address'), "B.Agraharam\npennagaram main road"));
$customerState = smt_state_text(smt_pick($invoiceData, array('customer_state', 'state'), '33-Tamil Nadu'));
$invoiceNo = trim((string)smt_pick($invoiceData, array('invoice_number', 'invoice_no', 'bill_no'), 'SMT/26-27/0837'));
$invoiceDate = smt_date_text(smt_pick($invoiceData, array('invoice_date', 'date', 'created_at'), ''), '22-06-2026');
$placeOfSupply = smt_state_text(smt_pick($invoiceData, array('place_of_supply', 'supply_state', 'customer_state', 'state'), '33-Tamil Nadu'));

$items = array();
$totalQty = 0;
$totalDiscount = 0;
$totalGst = 0;
$subTotalFromItems = 0;
$taxSummary = array();

foreach ($sourceItems as $sourceRow) {
    if (!is_array($sourceRow)) {
        continue;
    }

    $name = trim((string)smt_pick($sourceRow, array('name', 'item_name', 'product_name_snapshot', 'product_name', 'description'), 'Manual Sale Item'));
    $hsn = trim((string)smt_pick($sourceRow, array('hsn', 'hsn_code', 'item_hsn_code'), ''));
    $qty = smt_float(smt_pick($sourceRow, array('qty', 'quantity'), 0));
    $unit = strtoupper(trim((string)smt_pick($sourceRow, array('unit', 'item_unit', 'product_unit', 'product_unit_name'), 'PCS')));
    $price = smt_float(smt_pick($sourceRow, array('price', 'unit_price', 'rate', 'sale_rate'), 0));
    $mrp = smt_float(smt_pick($sourceRow, array('mrp', 'original_price', 'product_mrp'), $price));
    $disc = smt_float(smt_pick($sourceRow, array('disc', 'discount_amount', 'discount'), 0));
    $discRate = smt_float(smt_pick($sourceRow, array('disc_rate', 'discount_rate', 'discount_percent'), 0));
    $cgstRate = smt_float(smt_pick($sourceRow, array('cgst_rate', 'item_cgst_rate', 'cgst_percent', 'cgst_percentage'), 0));
    $sgstRate = smt_float(smt_pick($sourceRow, array('sgst_rate', 'item_sgst_rate', 'sgst_percent', 'sgst_percentage'), 0));
    $igstRate = smt_float(smt_pick($sourceRow, array('igst_rate', 'item_igst_rate', 'igst_percent', 'igst_percentage'), 0));
    $cgstAmount = smt_float(smt_pick($sourceRow, array('cgst_amount', 'item_cgst_amount', 'cgst', 'central_tax_amount'), 0));
    $sgstAmount = smt_float(smt_pick($sourceRow, array('sgst_amount', 'item_sgst_amount', 'sgst', 'state_tax_amount'), 0));
    $igstAmount = smt_float(smt_pick($sourceRow, array('igst_amount', 'item_igst_amount', 'igst', 'integrated_tax_amount'), 0));
    $gstAmount = smt_float(smt_pick($sourceRow, array('gst', 'gst_amount', 'tax_amount', 'total_tax', 'total_gst_amount', 'tax_value'), $cgstAmount + $sgstAmount + $igstAmount));
    $gstRate = smt_float(smt_pick($sourceRow, array('gst_rate', 'tax_rate', 'gst_percent', 'gst_percentage', 'tax_percent'), $cgstRate + $sgstRate + $igstRate));
    $amount = smt_float(smt_pick($sourceRow, array('amount', 'total_with_gst', 'line_total', 'total_price', 'row_total', 'net_amount'), (($qty * $price) - $disc + $gstAmount)));
    $taxable = smt_float(smt_pick($sourceRow, array('taxable', 'taxable_value', 'taxable_amount', 'taxable_total', 'sub_total'), max(0, $amount - $gstAmount)));

    // Some dynamic item queries return only total GST rate/amount, not separate CGST/SGST rows.
    // Build the missing split here so the bottom Tax Summary is always updated.
    if ($gstAmount <= 0 && ($cgstAmount + $sgstAmount + $igstAmount) > 0) {
        $gstAmount = $cgstAmount + $sgstAmount + $igstAmount;
    }

    if ($gstRate <= 0 && ($cgstRate + $sgstRate + $igstRate) > 0) {
        $gstRate = $cgstRate + $sgstRate + $igstRate;
    }

    if ($taxable <= 0 && $amount > 0 && $gstAmount > 0) {
        $taxable = max(0, $amount - $gstAmount);
    }

    if ($gstRate <= 0 && $gstAmount > 0 && $taxable > 0) {
        $gstRate = ($gstAmount / $taxable) * 100;
    }

    if (($cgstAmount + $sgstAmount + $igstAmount) <= 0.0001 && $gstAmount > 0 && $gstRate > 0) {
        $companyStateCode = '';
        $supplyStateCode = '';
        if (preg_match('/^\s*(\d+)/', (string)$companyState, $m)) {
            $companyStateCode = $m[1];
        }
        if (preg_match('/^\s*(\d+)/', (string)$placeOfSupply, $m)) {
            $supplyStateCode = $m[1];
        }

        $isIntraState = ($companyStateCode === '' || $supplyStateCode === '' || $companyStateCode === $supplyStateCode);

        if ($isIntraState) {
            $sgstRate = $gstRate / 2;
            $cgstRate = $gstRate / 2;
            $sgstAmount = round($gstAmount / 2, 2);
            $cgstAmount = round($gstAmount - $sgstAmount, 2);
            $igstRate = 0;
            $igstAmount = 0;
        } else {
            $igstRate = $gstRate;
            $igstAmount = $gstAmount;
            $sgstRate = 0;
            $cgstRate = 0;
            $sgstAmount = 0;
            $cgstAmount = 0;
        }
    }

    if ($discRate <= 0 && $disc > 0 && ($qty * $mrp) > 0) {
        $discRate = ($disc / ($qty * $mrp)) * 100;
    }

    $items[] = array(
        'name' => $name,
        'hsn' => $hsn,
        'mrp' => $mrp,
        'qty' => $qty,
        'unit' => $unit,
        'price' => $price,
        'disc' => $disc,
        'disc_rate' => $discRate,
        'gst' => $gstAmount,
        'gst_rate' => $gstRate,
        'amount' => $amount,
        'taxable' => $taxable,
        'cgst_rate' => $cgstRate,
        'sgst_rate' => $sgstRate,
        'igst_rate' => $igstRate,
        'cgst_amount' => $cgstAmount,
        'sgst_amount' => $sgstAmount,
        'igst_amount' => $igstAmount
    );

    $totalQty += $qty;
    $totalDiscount += $disc;
    $totalGst += $gstAmount;
    $subTotalFromItems += $amount;

    $components = array(
        array('type' => 'SGST', 'rate' => $sgstRate, 'amount' => $sgstAmount),
        array('type' => 'CGST', 'rate' => $cgstRate, 'amount' => $cgstAmount),
        array('type' => 'IGST', 'rate' => $igstRate, 'amount' => $igstAmount)
    );

    foreach ($components as $component) {
        if ($component['rate'] > 0 || $component['amount'] > 0) {
            $key = $component['type'] . '|' . $component['rate'];
            if (!isset($taxSummary[$key])) {
                $taxSummary[$key] = array('type' => $component['type'], 'rate' => $component['rate'], 'taxable' => 0, 'amount' => 0);
            }
            $taxSummary[$key]['taxable'] += $taxable;
            $taxSummary[$key]['amount'] += $component['amount'];
        }
    }
}

$subTotal = smt_float(smt_pick($invoiceData, array('sub_total', 'subtotal', 'total_before_roundoff', 'total_before_round_off'), 0));
if ($subTotal <= 0) {
    $subTotal = $subTotalFromItems;
}
$grandTotal = smt_float(smt_pick($invoiceData, array('total', 'grand_total', 'net_total', 'final_amount'), round($subTotal)));
$roundOff = smt_float(smt_pick($invoiceData, array('round_off', 'roundoff', 'round_off_amount'), $grandTotal - $subTotal));
$roundOffSign = ($roundOff < 0) ? '-' : (($roundOff > 0) ? '+' : '');
$roundOffAmount = abs($roundOff);
$amountWords = trim((string)smt_pick($invoiceData, array('amount_in_words', 'invoice_amount_words'), ''));
if ($amountWords === '') {
    $amountWords = smt_number_words((int)round($grandTotal)) . ' Rupees only';
}

$taxRows = array_values($taxSummary);
if (empty($taxRows) && isset($taxable_by_rate) && is_array($taxable_by_rate)) {
    foreach ($taxable_by_rate as $rate => $row) {
        if (!empty($row['sgst'])) {
            $taxRows[] = array('type' => 'SGST', 'taxable' => smt_float($row['taxable'] ?? 0), 'rate' => ((float)$rate / 2), 'amount' => smt_float($row['sgst']));
        }
        if (!empty($row['cgst'])) {
            $taxRows[] = array('type' => 'CGST', 'taxable' => smt_float($row['taxable'] ?? 0), 'rate' => ((float)$rate / 2), 'amount' => smt_float($row['cgst']));
        }
        if (!empty($row['igst'])) {
            $taxRows[] = array('type' => 'IGST', 'taxable' => smt_float($row['taxable'] ?? 0), 'rate' => (float)$rate, 'amount' => smt_float($row['igst']));
        }
    }
}
if (empty($taxRows)) {
    $fallbackTaxSummary = array();
    foreach ($items as $row) {
        $gstAmount = smt_float($row['gst'] ?? 0);
        $gstRate = smt_float($row['gst_rate'] ?? 0);
        $taxable = smt_float($row['taxable'] ?? 0);
        if ($gstAmount <= 0 || $gstRate <= 0) {
            continue;
        }

        $companyStateCode = '';
        $supplyStateCode = '';
        if (preg_match('/^\s*(\d+)/', (string)$companyState, $m)) {
            $companyStateCode = $m[1];
        }
        if (preg_match('/^\s*(\d+)/', (string)$placeOfSupply, $m)) {
            $supplyStateCode = $m[1];
        }
        $isIntraState = ($companyStateCode === '' || $supplyStateCode === '' || $companyStateCode === $supplyStateCode);

        if ($isIntraState) {
            $parts = array(
                array('type' => 'SGST', 'rate' => $gstRate / 2, 'amount' => round($gstAmount / 2, 2)),
                array('type' => 'CGST', 'rate' => $gstRate / 2, 'amount' => round($gstAmount - round($gstAmount / 2, 2), 2))
            );
        } else {
            $parts = array(array('type' => 'IGST', 'rate' => $gstRate, 'amount' => $gstAmount));
        }

        foreach ($parts as $part) {
            $key = $part['type'] . '|' . $part['rate'];
            if (!isset($fallbackTaxSummary[$key])) {
                $fallbackTaxSummary[$key] = array('type' => $part['type'], 'rate' => $part['rate'], 'taxable' => 0, 'amount' => 0);
            }
            $fallbackTaxSummary[$key]['taxable'] += $taxable;
            $fallbackTaxSummary[$key]['amount'] += $part['amount'];
        }
    }
    $taxRows = array_values($fallbackTaxSummary);
}

usort($taxRows, function ($a, $b) {
    if ($a['rate'] == $b['rate']) {
        return strcmp($a['type'], $b['type']);
    }
    return ($a['rate'] < $b['rate']) ? -1 : 1;
});
$taxRows = array_slice($taxRows, 0, 4);

$bank = !empty($bankRows) && is_array($bankRows[0]) ? $bankRows[0] : array(
    'bank_name' => 'SOUTH INDIAN BANK',
    'branch_name' => 'NALLANUR BRANCH',
    'account_number' => '0868073000000131',
    'ifsc_code' => 'SIBL0000868',
    'account_holder_name' => 'S M TRADERS'
);
$bankName = trim((string)smt_pick($bank, array('bank_name', 'name'), 'SOUTH INDIAN BANK'));
$branchName = trim((string)smt_pick($bank, array('branch_name', 'branch'), 'NALLANUR BRANCH'));
$bankLine = 'Name : ' . trim($bankName . ($branchName !== '' ? ' , ' . $branchName : ''));
$bankAccountNo = 'Account No. : ' . trim((string)smt_pick($bank, array('account_number', 'account_no'), '0868073000000131'));
$bankIfsc = 'IFSC code : ' . trim((string)smt_pick($bank, array('ifsc_code', 'ifsc'), 'SIBL0000868'));
$bankHolder = "Account holder's name : " . trim((string)smt_pick($bank, array('account_holder_name', 'holder_name'), 'S M TRADERS'));
$termsText = trim((string)smt_pick($settingsData, array('invoice_terms', 'terms', 'terms_conditions'), 'Thanks for doing business with us!'));
if (strpos($termsText, "\n") !== false) {
    $parts = preg_split('/\r\n|\r|\n/', $termsText);
    $termsText = trim((string)$parts[0]);
}
if ($termsText === '') {
    $termsText = 'Thanks for doing business with us!';
}


$pdf = new ExactSMTInvoicePDF('P', 'pt', array(595.92, 841.92));
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();
$pdf->SetTitle('Tax Invoice - ' . $invoiceNo);
$pdf->SetAuthor($companyName);
$pdf->SetDrawColor(45, 45, 45);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetLineWidth(0.45);

$x0 = 45.75;
$x1 = 556.50;
$split = 301.50;
$outerBottom = 820.00;

// Main outer box and header.
$pdf->fitText($x0, 28.00, $x1 - $x0, 14.00, 'Tax Invoice', 'C', 'B', 11.5);
$pdf->Rect($x0, 45.75, $x1 - $x0, $outerBottom - 45.75);
$pdf->Line($x0, 102.75, $x1, 102.75);
$pdf->fitText($x0, 50.00, $x1 - $x0, 18.00, $companyName, 'C', 'B', 16.0);
$pdf->fitText($x0, 68.00, $x1 - $x0, 10.00, $companyAddress, 'C', '', 8.5);
$pdf->fitText($x0, 79.30, $x1 - $x0, 10.00, 'Phone no.: ' . $companyPhone, 'C', '', 8.5);
$pdf->fitText($x0, 90.50, $x1 - $x0, 10.00, 'GSTIN: ' . $companyGstin . ', State: ' . $companyState, 'C', '', 8.5);

// Bill-to and invoice details.
$pdf->Line($split, 102.75, $split, 192.00);
$pdf->Line($x0, 118.50, $x1, 118.50);
$pdf->Line($x0, 192.00, $x1, 192.00);
$pdf->fitText($x0 + 3, 104.75, ($split - $x0) - 6, 12, 'Bill To', 'L', 'B', 8.5);
$pdf->fitText($split + 3, 104.75, ($x1 - $split) - 6, 12, 'Invoice Details', 'R', 'B', 8.5);
$pdf->fitText($x0 + 3, 121.0, $split - $x0 - 6, 12, $customerName, 'L', 'B', 8.5);
$pdf->blockText($x0 + 3, 137.0, $split - $x0 - 6, 23, $customerAddress, 'L', '', 8.5, 8.0, 10.8);
$pdf->fitText($x0 + 3, 162.3, $split - $x0 - 6, 11, 'Contact No. : ' . $customerPhone, 'L', '', 8.5);
$pdf->fitText($x0 + 3, 178.0, $split - $x0 - 6, 11, 'State: ' . $customerState, 'L', '', 8.5);
$pdf->fitText($split + 3, 121.0, $x1 - $split - 6, 11, 'Invoice No. : ' . $invoiceNo, 'R', '', 8.5);
$pdf->fitText($split + 3, 137.0, $x1 - $split - 6, 11, 'Date : ' . $invoiceDate, 'R', '', 8.5);
$pdf->fitText($split + 3, 152.2, $x1 - $split - 6, 11, 'Place of supply: ' . $placeOfSupply, 'R', '', 8.5);

// Product table grid.
$xs = array(45.75, 65.25, 185.00, 226.00, 265.00, 317.00, 360.00, 408.00, 461.00, 508.00, 556.50);
$tableTop = 192.00;
$headerBottom = 210.00;
$rowHeights = array();
foreach ($items as $line) {
    $lineCount = max(1, count($pdf->wrapLines($line['name'], $xs[2] - $xs[1] - 8, 'B', 8.0)));
    $rowHeight = max(22, min(42, 12 + ($lineCount * 11)));

    // If dynamic currency values are large, give the row enough height for automatic 2-line fitting.
    $discountText = $pdf->moneyNumber($line['disc']) . ' (' . $pdf->percent($line['disc_rate']) . '%)';
    $gstText = $pdf->moneyNumber($line['gst']) . ' (' . $pdf->percent($line['gst_rate']) . '%)';
    if (strlen($discountText) > 13 || strlen($gstText) > 13) {
        $rowHeight = max($rowHeight, 31);
    }

    $rowHeights[] = $rowHeight;
}
if (empty($rowHeights)) {
    $rowHeights = array(28);
}
$maxBodyHeight = 357.00;
$bodyHeight = array_sum($rowHeights);
if ($bodyHeight > $maxBodyHeight) {
    $ratio = $maxBodyHeight / $bodyHeight;
    foreach ($rowHeights as $i => $h) {
        $rowHeights[$i] = max(18, floor($h * $ratio));
    }
}
$rowTops = array();
$rowBots = array();
$y = $headerBottom;
foreach ($rowHeights as $h) {
    $rowTops[] = $y;
    $y += $h;
    $rowBots[] = $y;
}
$totalTop = $y;
$totalBottom = $totalTop + 20;
$taxTop = $totalBottom;
$taxBottom = $taxTop + 88;
$wordsTop = $taxBottom;
$wordsBottom = $wordsTop + 46;
$bankTop = $wordsBottom;
$bankBottom = $outerBottom;

// Vertical column lines. Body has no inner horizontal row boxes.
for ($i = 0; $i < count($xs); $i++) {
    $pdf->Line($xs[$i], $tableTop, $xs[$i], $totalBottom);
}
$pdf->Line($x0, $tableTop, $x1, $tableTop);
$pdf->Line($x0, $headerBottom, $x1, $headerBottom);
$pdf->Line($x0, $totalTop, $x1, $totalTop);
$pdf->Line($x0, $totalBottom, $x1, $totalBottom);

$headers = array('#', 'Item name', 'HSN/ SAC', 'MRP', 'Quantity', 'Unit', 'Price/ Unit', 'Discount', 'GST', 'Amount');
$align = array('C', 'L', 'C', 'R', 'R', 'R', 'R', 'R', 'R', 'R');
for ($i = 0; $i < 10; $i++) {
    $pdf->fitText($xs[$i] + 3, $tableTop + 4, ($xs[$i + 1] - $xs[$i]) - 6, 10, $headers[$i], $align[$i], 'B', 8.3, 5.7);
}

foreach ($items as $r => $row) {
    $yt = $rowTops[$r];
    $yb = $rowBots[$r];
    $rh = $yb - $yt;
    $cy = $yt + (($rh - 10) / 2);

    $pdf->fitText($xs[0] + 2, $cy, $xs[1] - $xs[0] - 4, 10, (string)($r + 1), 'L', '', 8.0);
    $pdf->blockText($xs[1] + 4, $yt + 2.0, $xs[2] - $xs[1] - 8, $rh - 3.0, $row['name'], 'L', 'B', 8.0, 6.6);
    $pdf->fitText($xs[2] + 3, $cy, $xs[3] - $xs[2] - 6, 10, $row['hsn'], 'L', '', 7.7, 5.5);
    $pdf->money($xs[3], $cy, $xs[4] - $xs[3], 10, $row['mrp'], 'R', '', 7.7);
    $pdf->fitText($xs[4] + 3, $cy, $xs[5] - $xs[4] - 6, 10, smt_qty_text($row['qty']), 'R', '', 8.0);
    $pdf->fitText($xs[5] + 3, $cy, $xs[6] - $xs[5] - 6, 10, $row['unit'], 'R', '', 8.0);
    $pdf->money($xs[6], $cy, $xs[7] - $xs[6], 10, $row['price'], 'R', '', 7.3);
    $pdf->moneyPercent($xs[7], $yt + 2.0, $xs[8] - $xs[7], $rh - 4.0, $row['disc'], $row['disc_rate'], 'R', '', 6.8);
    $pdf->moneyPercent($xs[8], $yt + 2.0, $xs[9] - $xs[8], $rh - 4.0, $row['gst'], $row['gst_rate'], 'R', '', 6.8);
    $pdf->money($xs[9], $cy, $xs[10] - $xs[9], 10, $row['amount'], 'R', '', 7.2);
}

// Total row.
$totalY = $totalTop + 5.0;
$pdf->fitText($xs[1] + 4, $totalY, $xs[2] - $xs[1] - 8, 11, 'Total', 'L', 'B', 8.6);
$pdf->fitText($xs[4] + 3, $totalY, $xs[5] - $xs[4] - 6, 11, smt_qty_text($totalQty), 'R', 'B', 8.6);
$pdf->money($xs[7], $totalY, $xs[8] - $xs[7], 11, $totalDiscount, 'R', 'B', 7.6);
$pdf->money($xs[8], $totalY, $xs[9] - $xs[8], 11, $totalGst, 'R', 'B', 7.6);
$pdf->money($xs[9], $totalY, $xs[10] - $xs[9], 11, $subTotal, 'R', 'B', 7.6);

// Tax and amounts section.
$pdf->Line($split, $taxTop, $split, $taxBottom);
$pdf->Line($x0, $taxBottom, $x1, $taxBottom);
$taxX = array($x0, 118.00, 188.00, 237.00, $split);
$taxHeadY = $taxTop + 8.0;
$pdf->fitText($taxX[0] + 4, $taxHeadY, $taxX[1] - $taxX[0] - 8, 10, 'Tax type', 'L', 'B', 8.3);
$pdf->fitText($taxX[1] + 3, $taxHeadY, $taxX[2] - $taxX[1] - 6, 10, 'Taxable amount', 'R', 'B', 8.3);
$pdf->fitText($taxX[2] + 3, $taxHeadY, $taxX[3] - $taxX[2] - 6, 10, 'Rate', 'R', 'B', 8.3);
$pdf->fitText($taxX[3] + 3, $taxHeadY, $taxX[4] - $taxX[3] - 6, 10, 'Tax amount', 'R', 'B', 8.3);
$pdf->fitText($split + 4, $taxHeadY, 80, 10, 'Amounts', 'L', 'B', 8.3);

$taxLineY = array($taxTop + 26, $taxTop + 42, $taxTop + 58, $taxTop + 74);
for ($i = 0; $i < 4; $i++) {
    if (!isset($taxRows[$i])) {
        continue;
    }
    $tax = $taxRows[$i];
    $pdf->fitText($taxX[0] + 4, $taxLineY[$i], $taxX[1] - $taxX[0] - 8, 10, $tax['type'], 'L', '', 8.2);
    $pdf->money($taxX[1], $taxLineY[$i], $taxX[2] - $taxX[1], 10, $tax['taxable'], 'R', '', 8.0);
    $pdf->fitText($taxX[2] + 3, $taxLineY[$i], $taxX[3] - $taxX[2] - 6, 10, smt_qty_text($tax['rate']) . '%', 'R', '', 8.2);
    $pdf->money($taxX[3], $taxLineY[$i], $taxX[4] - $taxX[3], 10, $tax['amount'], 'R', '', 8.0);
}

$pdf->fitText($split + 4, $taxTop + 26, 82, 10, 'Sub Total', 'L', '', 8.2);
$pdf->money($x1 - 102, $taxTop + 26, 100, 10, $subTotal, 'R', '', 8.0);
$pdf->fitText($split + 4, $taxTop + 42, 82, 10, 'Round off', 'L', '', 8.2);
$pdf->fitText($x1 - 102, $taxTop + 42, 18, 10, $roundOffSign, 'R', '', 8.2);
$pdf->money($x1 - 84, $taxTop + 42, 82, 10, $roundOffAmount, 'R', '', 8.0);
$pdf->Line($split, $taxTop + 56, $x1, $taxTop + 56);
$pdf->fitText($split + 4, $taxTop + 62, 82, 12, 'Total', 'L', 'B', 8.5);
$pdf->money($x1 - 102, $taxTop + 62, 100, 12, $grandTotal, 'R', 'B', 8.4);

// Invoice amount in words.
$pdf->Line($x0, $wordsTop, $x1, $wordsTop);
$pdf->Line($split, $taxTop, $split, $bankBottom);
$pdf->Line($x0, $wordsBottom, $x1, $wordsBottom);
$pdf->fitText($x0, $wordsTop + 6, $split - $x0, 12, 'Invoice Amount In Words', 'C', 'B', 8.4);
$pdf->fitText($x0 + 10, $wordsTop + 27, ($split - $x0) - 20, 10, $amountWords, 'C', '', 8.2);

// Bank details and terms.
$pdf->fitText($x0 + 6, $bankTop + 8, $split - $x0 - 12, 10, 'Bank Details', 'L', 'B', 8.4);
$pdf->fitText($x0 + 6, $bankTop + 28, $split - $x0 - 12, 10, $bankLine, 'L', '', 8.2);
$pdf->fitText($x0 + 6, $bankTop + 44, $split - $x0 - 12, 10, $bankAccountNo, 'L', '', 8.2);
$pdf->fitText($x0 + 6, $bankTop + 60, $split - $x0 - 12, 10, $bankIfsc, 'L', '', 8.2);
$pdf->fitText($x0 + 6, $bankTop + 76, $split - $x0 - 12, 10, $bankHolder, 'L', '', 8.2);
$pdf->fitText($split + 6, $bankTop + 8, $x1 - $split - 12, 10, 'Terms and Conditions', 'L', 'B', 8.4);
$pdf->fitText($split + 6, $bankTop + 28, $x1 - $split - 12, 10, $termsText, 'L', '', 8.2);

$safeInvoiceNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoiceNo);

if (defined('INVOICE_DESIGN_LOADED')) {
    return;
}

$pdf->Output('I', 'Tax Invoice_' . $safeInvoiceNo . '_no_overlap.pdf');
exit;
