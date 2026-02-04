<?php
require_once __DIR__ . "/vendor/autoload.php";

$pdfPath = __DIR__ . "/../uploads/pdf_template.pdf";

if (!file_exists($pdfPath)) {
    die("PDF not found at: $pdfPath\n");
}

echo "Checking PDF: $pdfPath\n";

$content = file_get_contents($pdfPath);

if (strpos($content, "/AcroForm") !== false) {
    echo "ACROFORM FOUND\n";
    
    preg_match_all("/\/T\s*(?:\((.*?)\)|<([0-9A-Fa-f]+)>)/", $content, $matches, PREG_SET_ORDER);
    
    $names = [];
    foreach ($matches as $m) {
        if (!empty($m[1])) {
            $names[] = $m[1];
        } elseif (!empty($m[2])) {
            $hex = $m[2];
            // Simple hex decode
            $str = "";
            for ($i=0; $i < strlen($hex)-1; $i+=2) {
                $str .= chr(hexdec(substr($hex, $i, 2)));
            }
            $names[] = $str;
        }
    }
    
    if (!empty($names)) {
        $names = array_unique($names);
        sort($names);
        
        echo "Fields found (" . count($names) . "):\n";
        foreach ($names as $fieldName) {
            echo "  - $fieldName\n";
        }
        
        $search = ["kennelName", "dogBreed", "dogGender", "buyerName"];
        echo "\nSpecific check:\n";
        foreach ($search as $s) {
            echo "  - $s: " . (in_array($s, $names) ? "FOUND" : "MISSING") . "\n";
        }
    } else {
        echo "NO FIELDS MATCHED REGEX.\n";
    }
} else {
    echo " No AcroForm found\n";
}
