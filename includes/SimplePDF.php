<?php
/**
 * Minimal dependency-free PDF writer.
 *
 * Used as a fallback when DomPDF/FPDF are not installed (the project's
 * composer.json includes both, but the app must work out of the box).
 * Supports text, lines and filled rectangles on A4 pages.
 */

declare(strict_types=1);

final class SimplePDF
{
    private float $width = 595.28;
    private float $height = 841.89;
    /** @var array<int,string> */
    private array $pages = [];
    private string $current = '';

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
            $this->current = '';
        }
    }

    public function pageWidth(): float
    {
        return $this->width;
    }

    public function pageHeight(): float
    {
        return $this->height;
    }

    /**
     * Add text. Coordinates are measured from the top-left corner.
     *
     * @param array{0:float,1:float,2:float} $rgb
     */
    public function text(float $x, float $topY, string $text, float $size = 12, bool $bold = false, array $rgb = [0.1, 0.1, 0.1]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $y = $this->height - $topY;
        $encoded = $this->encode($text);
        $color = sprintf('%.3F %.3F %.3F rg', $rgb[0], $rgb[1], $rgb[2]);

        $this->current .= sprintf(
            "BT /%s %.2F Tf %s 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $font,
            $size,
            $color,
            $x,
            $y,
            $encoded
        );
    }

    /**
     * @param array{0:float,1:float,2:float} $rgb
     */
    public function line(float $x1, float $topY1, float $x2, float $topY2, float $width = 0.8, array $rgb = [0.8, 0.85, 0.9]): void
    {
        $y1 = $this->height - $topY1;
        $y2 = $this->height - $topY2;
        $this->current .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $width,
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    /**
     * @param array{0:float,1:float,2:float} $fill
     */
    public function rect(float $x, float $topY, float $width, float $height, array $fill = [0.96, 0.97, 0.99]): void
    {
        $y = $this->height - $topY - $height;
        $this->current .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $fill[0],
            $fill[1],
            $fill[2],
            $x,
            $y,
            $width,
            $height
        );
    }

    public function output(string $filename = 'document.pdf'): never
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
            $this->current = '';
        }

        $pdf = $this->build();

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'document.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    private function build(): string
    {
        $pageCount = count($this->pages);
        $objects = [];

        // 1 = catalog, 2 = pages tree, 3 = Helvetica, 4 = Helvetica-Bold.
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $i => $content) {
            $pageObj = 5 + $i * 2;
            $contentObj = $pageObj + 1;

            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->width . ' ' . $this->height . ']'
                . ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>'
                . ' /Contents ' . $contentObj . ' 0 R >>';
            $objects[$contentObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObj = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $maxObj; $n++) {
            if (isset($offsets[$n])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function encode(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $converted = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }
}
