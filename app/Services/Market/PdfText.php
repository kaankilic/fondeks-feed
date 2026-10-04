<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Process;

/**
 * Turns a report PDF into column-aligned plain text with poppler's pdftotext,
 * so extraction can send a few thousand tokens of text instead of the PDF
 * billed as per-page images.
 *
 * The PDF never touches disk: its bytes are piped to pdftotext over stdin and
 * the text read back over stdout. The command is passed as an argument vector
 * (no shell), so the PDF content can never be interpreted as a command, and the
 * binary is taken from configuration so it can be pinned to an absolute path.
 * A conversion that fails or yields no usable text throws — the caller must not
 * fall back to sending the PDF.
 */
class PdfText
{
    /** Below this many characters the output is treated as a failed/scanned read. */
    private const MIN_CHARS = 20;

    public function toLayoutText(string $pdf): string
    {
        if ($pdf === '') {
            throw new \RuntimeException('cannot extract text from an empty PDF');
        }

        $binary = (string) config('ingest.pdftotext.bin', 'pdftotext');
        $timeout = (int) config('ingest.pdftotext.timeout', 60);

        // "-" reads the PDF from stdin and writes the text to stdout, so nothing
        // is ever written to disk. -layout keeps the table columns aligned.
        $result = Process::timeout($timeout)
            ->input($pdf)
            ->run([$binary, '-layout', '-nopgbrk', '-q', '-enc', 'UTF-8', '-', '-']);

        if (! $result->successful()) {
            $reason = trim($result->errorOutput()) ?: 'exit code ' . $result->exitCode();
            throw new \RuntimeException('pdftotext failed: ' . mb_substr($reason, 0, 200));
        }

        $text = trim($result->output());
        if (mb_strlen($text) < self::MIN_CHARS) {
            throw new \RuntimeException('pdftotext produced no usable text (image-only or scanned PDF?)');
        }

        return $text;
    }
}
