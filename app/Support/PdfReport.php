<?php

namespace App\Support;

/**
 * Small dependency-free PDF writer (A4) supporting headings, paragraphs and
 * tables. Enough for printable evaluation reports without adding a Composer
 * package.
 */
class PdfReport
{
    protected float $pageWidth;

    protected float $pageHeight;

    protected float $margin = 34;

    protected array $pages = [];

    protected int $current = 0;

    protected float $cursorY = 0;

    protected string $title = '';

    protected string $footer = '';

    protected array $brand = [0.31, 0.275, 0.898];

    protected array $ink = [0.12, 0.16, 0.24];

    protected array $muted = [0.42, 0.46, 0.55];

    protected array $stripe = [0.968, 0.973, 0.988];

    public function __construct(string $orientation = 'landscape')
    {
        if ($orientation === 'portrait') {
            $this->pageWidth = 595.28;
            $this->pageHeight = 841.89;
        } else {
            $this->pageWidth = 841.89;
            $this->pageHeight = 595.28;
        }

        $this->newPage();
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function setFooter(string $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    public function heading(string $text, float $size = 18): static
    {
        $this->writeWrapped($text, $size, true, $this->ink, 4, 6);

        return $this;
    }

    public function subheading(string $text): static
    {
        $this->writeWrapped($text, 11, false, $this->muted, 0, 8);

        return $this;
    }

    public function sectionTitle(string $text): static
    {
        $this->ensureSpace(28);
        $this->cursorY += 6;
        $this->textAt($this->margin, $this->cursorY + 4, $text, 11.5, true, $this->ink);
        $this->line($this->margin, $this->cursorY + 20, $this->pageWidth - $this->margin, $this->cursorY + 20, [0.87, 0.89, 0.93]);
        $this->cursorY += 26;

        return $this;
    }

    public function paragraph(string $text, float $size = 9.2, bool $bold = false): static
    {
        $this->writeWrapped($text, $size, $bold, $this->ink, 0, 6);

        return $this;
    }

    public function metaLine(string $label, string $value): static
    {
        $this->ensureSpace(16);
        $this->textAt($this->margin, $this->cursorY, $label, 8.5, false, $this->muted);
        $this->textAt($this->margin + 95, $this->cursorY, $value, 8.5, true, $this->ink);
        $this->cursorY += 15;

        return $this;
    }

    public function spacer(float $height = 10): static
    {
        $this->cursorY += $height;

        return $this;
    }

    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,mixed>>  $rows
     * @param  array<int,float>|null  $widths  Relative column widths.
     * @param  array<int,string>  $aligns  left|right|center per column.
     */
    public function table(array $headers, array $rows, ?array $widths = null, array $aligns = []): static
    {
        $columnCount = count($headers);
        $widths = $widths ?: array_fill(0, $columnCount, 1 / max($columnCount, 1));
        $total = array_sum($widths) ?: 1;
        $widths = array_map(fn ($w) => ($w / $total) * ($this->pageWidth - 2 * $this->margin), $widths);

        $this->drawTableHeader($headers, $widths, $aligns);

        foreach ($rows as $index => $row) {
            $cells = array_map(fn ($value) => $this->normaliseCell($value), array_values($row));
            $cells = array_pad($cells, $columnCount, '');

            $lines = [];
            $maxLines = 1;
            foreach ($widths as $i => $width) {
                $lines[$i] = $this->wrapText($cells[$i], $width - 10, 8, $aligns[$i] ?? 'left');
                $maxLines = max($maxLines, count($lines[$i]));
            }

            $rowHeight = $maxLines * 10.5 + 7;

            if ($this->cursorY + $rowHeight > $this->pageHeight - 46) {
                $this->newPage();
                $this->drawTableHeader($headers, $widths, $aligns);
            }

            $top = $this->cursorY;
            if ($index % 2 === 1) {
                $this->rect($this->margin, $top, $this->pageWidth - 2 * $this->margin, $rowHeight, $this->stripe);
            }

            $x = $this->margin;
            foreach ($widths as $i => $width) {
                $align = $aligns[$i] ?? 'left';
                foreach ($lines[$i] as $lineIndex => $line) {
                    $textWidth = $this->textWidth($line, 8);
                    $textX = match ($align) {
                        'right' => $x + $width - 5 - $textWidth,
                        'center' => $x + ($width - $textWidth) / 2,
                        default => $x + 5,
                    };
                    $this->textAt($textX, $top + 5 + $lineIndex * 10.5, $line, 8, false, $this->ink);
                }
                $x += $width;
            }

            $this->cursorY += $rowHeight;
        }

        $this->cursorY += 4;

        return $this;
    }

    public function output(): string
    {
        $pageCount = count($this->pages);

        foreach ($this->pages as $index => &$page) {
            $content = $page['content'];
            $content .= $this->footerCommand($index + 1, $pageCount);
            $page['content'] = $content;
        }
        unset($page);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $objectNumber = 5;

        foreach ($this->pages as $page) {
            $pageObject = $objectNumber++;
            $contentObject = $objectNumber++;
            $kids[] = $pageObject.' 0 R';
            $stream = $page['content'];
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->n($this->pageWidth).' '.$this->n($this->pageHeight).'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            if (isset($offsets[$i])) {
                $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }
        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    protected function drawTableHeader(array $headers, array $widths, array $aligns): void
    {
        $height = 19;
        if ($this->cursorY + $height + 24 > $this->pageHeight - 46) {
            $this->newPage();
        }

        $top = $this->cursorY;
        $this->rect($this->margin, $top, $this->pageWidth - 2 * $this->margin, $height, $this->brand);

        $x = $this->margin;
        foreach ($widths as $i => $width) {
            $header = $this->normaliseCell($headers[$i] ?? '');
            $header = $this->truncate($header, $width - 10, 8);
            $textWidth = $this->textWidth($header, 8);
            $align = $aligns[$i] ?? 'left';
            $textX = match ($align) {
                'right' => $x + $width - 5 - $textWidth,
                'center' => $x + ($width - $textWidth) / 2,
                default => $x + 5,
            };
            $this->textAt($textX, $top + 5, $header, 8, true, [1, 1, 1]);
            $x += $width;
        }

        $this->cursorY += $height;
    }

    protected function writeWrapped(string $text, float $size, bool $bold, array $color, float $spaceBefore, float $spaceAfter): void
    {
        $maxWidth = $this->pageWidth - 2 * $this->margin;
        $lines = $this->wrapText($text, $maxWidth, $size, 'left', $bold);

        $this->cursorY += $spaceBefore;
        foreach ($lines as $line) {
            $lineHeight = $size * 1.4;
            if ($this->cursorY + $lineHeight > $this->pageHeight - 46) {
                $this->newPage();
            }
            $this->textAt($this->margin, $this->cursorY + $lineHeight * 0.2, $line, $size, $bold, $color);
            $this->cursorY += $lineHeight;
        }
        $this->cursorY += $spaceAfter;
    }

    protected function wrapText(string $text, float $maxWidth, float $size, string $align = 'left', bool $bold = false): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($this->normaliseCell($text)));
        if ($text === '') {
            return [''];
        }

        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($this->textWidth($candidate, $size, $bold) <= $maxWidth || $line === '') {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    protected function truncate(string $text, float $maxWidth, float $size): string
    {
        if ($this->textWidth($text, $size) <= $maxWidth) {
            return $text;
        }

        while ($text !== '' && $this->textWidth($text.'...', $size) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return $text.'...';
    }

    protected function textWidth(string $text, float $size, bool $bold = false): float
    {
        $factor = $bold ? 0.54 : 0.5;

        return mb_strlen($text) * $size * $factor;
    }

    protected function textAt(float $x, float $top, string $text, float $size, bool $bold = false, array $color = [0, 0, 0]): void
    {
        if ($text === '') {
            return;
        }

        $baseline = $this->pageHeight - $top - $size * 0.8;
        $command = 'BT /F'.($bold ? '2' : '1').' '.$this->n($size).' Tf '
            .$this->rgb($color).' rg 1 0 0 1 '.$this->n($x).' '.$this->n($baseline).' Tm ('
            .$this->escape($text).") Tj ET\n";
        $this->pages[$this->current]['content'] .= $command;
    }

    protected function rect(float $x, float $top, float $width, float $height, array $color): void
    {
        $y = $this->pageHeight - $top - $height;
        $this->pages[$this->current]['content'] .= $this->rgb($color).' rg '
            .$this->n($x).' '.$this->n($y).' '.$this->n($width).' '.$this->n($height)." re f\n";
    }

    protected function line(float $x1, float $top1, float $x2, float $top2, array $color): void
    {
        $this->pages[$this->current]['content'] .= $this->rgb($color).' RG 0.7 w '
            .$this->n($x1).' '.$this->n($this->pageHeight - $top1).' m '
            .$this->n($x2).' '.$this->n($this->pageHeight - $top2)." l S\n";
    }

    protected function footerCommand(int $page, int $total): string
    {
        $footerText = $this->footer !== '' ? $this->footer : $this->title;
        $footerText = mb_substr($footerText, 0, 110);
        $left = $this->escape($footerText);
        $right = 'Halaman '.$page.' dari '.$total;
        $y = 26;

        return $this->rgb($this->muted)." rg 0.7 w "
            .$this->n($this->margin).' '.$this->n($y + 10).' m '
            .$this->n($this->pageWidth - $this->margin).' '.$this->n($y + 10)." l S\n"
            .'BT /F1 7.5 Tf '.$this->rgb($this->muted).' rg 1 0 0 1 '.$this->n($this->margin).' '.$this->n($y).' Tm ('.$left.") Tj ET\n"
            .'BT /F1 7.5 Tf '.$this->rgb($this->muted).' rg 1 0 0 1 '
            .$this->n($this->pageWidth - $this->margin - $this->textWidth($right, 7.5)).' '.$this->n($y).' Tm ('
            .$this->escape($right).") Tj ET\n";
    }

    protected function newPage(): void
    {
        $this->pages[] = ['content' => ''];
        $this->current = count($this->pages) - 1;
        $this->cursorY = $this->margin;
    }

    protected function ensureSpace(float $height): void
    {
        if ($this->cursorY + $height > $this->pageHeight - 46) {
            $this->newPage();
        }
    }

    protected function normaliseCell(mixed $value): string
    {
        $text = str_replace(
            ['—', '–', '“', '”', '‘', '’', '•', '·'],
            ['-', '-', '"', '"', "'", "'", '-', '-'],
            (string) $value
        );

        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }

    protected function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
    }

    protected function rgb(array $color): string
    {
        return implode(' ', array_map(fn ($c) => $this->n((float) $c), $color));
    }

    protected function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
