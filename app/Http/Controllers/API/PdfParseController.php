<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Spatie\PdfToText\Pdf;

class PdfParseController extends Controller
{
    public function parse(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $path = $request->file('file')->getPathname();

        try {
            // Читаем PDF через poppler
            $rawText = Pdf::getText($path);

            // Фикс мусора
            $rawText = iconv("UTF-8", "UTF-8//IGNORE", $rawText);
            $rawText = mb_convert_encoding($rawText, 'UTF-8', 'UTF-8');

            $clean = $this->cleanText($rawText);
        } catch (\Exception $e) {
            return response()->json([
                "error" => "PDF parse error: " . $e->getMessage()
            ], 500);
        }

        return response()->json([
            "raw" => $rawText,
            "clean" => $clean
        ]);
    }

    public function parse1(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $pdf = $request->file('file');

        try {
            $parser = new Parser();
            $pdfObj = $parser->parseFile($pdf->getPathname());
            $rawText = $pdfObj->getText();
            $rawText = mb_convert_encoding($rawText, 'UTF-8', 'auto');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ошибка чтения PDF: ' . $e->getMessage()
            ], 500);
        }

        // --- Чистим текст ---
        $clean = $this->cleanText($rawText);

        return response()->json([
            'raw' => $rawText,
            'clean' => $clean,
        ]);
    }

    private function cleanText($text)
    {
        // Удаляем control-символы (\u0000 и др.)
        $text = preg_replace('/[\x00-\x1F\x80-\x9F]/u', ' ', $text);

        // Удаляем лишние пробелы
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
