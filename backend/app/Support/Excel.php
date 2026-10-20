<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arma un .xlsx sencillo: una o varias hojas con encabezado en negrita,
 * filtro, primera fila fija y columnas ajustadas.
 */
class Excel
{
    private Spreadsheet $libro;

    private bool $primera = true;

    public function __construct()
    {
        $this->libro = new Spreadsheet;
    }

    /**
     * @param  string[]  $encabezados
     * @param  iterable<array>  $filas
     * @param  array<int, string>  $formatos  índice de columna (0..) => formato numérico
     */
    public function hoja(string $titulo, array $encabezados, iterable $filas, array $formatos = []): self
    {
        $hoja = $this->primera ? $this->libro->getActiveSheet() : $this->libro->createSheet();
        $this->primera = false;
        $hoja->setTitle(mb_substr($titulo, 0, 31));

        $hoja->fromArray($encabezados, null, 'A1');
        $n = 1;
        foreach ($filas as $fila) {
            $hoja->fromArray(array_values($fila), null, 'A'.(++$n), true);
        }

        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $hoja->getStyle("A1:{$ultima}1")->getFill()->setFillType('solid')->getStartColor()->setRGB('E2E8F0');
        $hoja->setAutoFilter("A1:{$ultima}{$n}");
        $hoja->freezePane('A2');

        foreach ($formatos as $col => $formato) {
            $letra = Coordinate::stringFromColumnIndex($col + 1);
            $hoja->getStyle("{$letra}2:{$letra}{$n}")->getNumberFormat()->setFormatCode($formato);
        }
        for ($c = 1; $c <= count($encabezados); $c++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return $this;
    }

    public function descargar(string $nombre): StreamedResponse
    {
        $this->libro->setActiveSheetIndex(0);
        $writer = new Xlsx($this->libro);

        return response()->streamDownload(fn () => $writer->save('php://output'), $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public const QUETZALES = '"Q" #,##0.00';

    public const FECHA = 'dd/mm/yyyy';

    public const FECHA_HORA = 'dd/mm/yyyy hh:mm';
}
