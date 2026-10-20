<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arma un .xlsx sencillo: cada hoja lleva arriba el logo, el nombre del negocio,
 * el título y la fecha; debajo la tabla con encabezado en negrita, filtro y
 * columnas ajustadas.
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

        $columnas = max(count($encabezados), 3);
        $ultima = Coordinate::stringFromColumnIndex($columnas);
        $this->encabezadoDelReporte($hoja, $titulo, $ultima);

        $inicio = self::FILA_TABLA;
        $hoja->fromArray($encabezados, null, "A{$inicio}");
        $n = $inicio;
        foreach ($filas as $fila) {
            $hoja->fromArray(array_values($fila), null, 'A'.(++$n), true);
        }

        $ultimaTabla = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->getStyle("A{$inicio}:{$ultimaTabla}{$inicio}")->getFont()->setBold(true);
        $hoja->getStyle("A{$inicio}:{$ultimaTabla}{$inicio}")->getFill()->setFillType('solid')->getStartColor()->setRGB('E2E8F0');
        $hoja->setAutoFilter("A{$inicio}:{$ultimaTabla}{$n}");
        $hoja->freezePane('A'.($inicio + 1));

        foreach ($formatos as $col => $formato) {
            $letra = Coordinate::stringFromColumnIndex($col + 1);
            $hoja->getStyle("{$letra}".($inicio + 1).":{$letra}{$n}")->getNumberFormat()->setFormatCode($formato);
        }
        for ($c = 1; $c <= count($encabezados); $c++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        // La primera columna debe dejar espacio al logo.
        $hoja->calculateColumnWidths();
        $a = $hoja->getColumnDimension('A');
        $a->setAutoSize(false)->setWidth(max(13, $a->getWidth()));

        return $this;
    }

    /** Filas 1-3: logo, nombre del negocio, título del reporte y fecha. La tabla empieza en FILA_TABLA. */
    private function encabezadoDelReporte($hoja, string $titulo, string $ultima): void
    {
        $logo = config('negocio.logo');
        if ($logo && is_file($logo)) {
            $dibujo = new Drawing;
            $dibujo->setName('Logo')->setPath($logo)->setHeight(62)->setCoordinates('A1')->setOffsetX(4)->setOffsetY(2);
            $dibujo->setWorksheet($hoja);
        }

        $textos = [
            [config('negocio.nombre'), 16, true, '0F172A'],
            [$titulo, 12, true, '334155'],
            ['Generado el '.now()->format('d/m/Y H:i'), 10, false, '64748B'],
        ];
        foreach ($textos as $i => [$texto, $tam, $negrita, $color]) {
            $fila = $i + 1;
            // Celdas combinadas: así el título no ensancha la columna B.
            $hoja->mergeCells("B{$fila}:{$ultima}{$fila}");
            $hoja->setCellValue("B{$fila}", $texto);
            $hoja->getStyle("B{$fila}")->getFont()->setSize($tam)->setBold($negrita)->getColor()->setRGB($color);
            $hoja->getRowDimension($fila)->setRowHeight(17);
        }
    }

    public function descargar(string $nombre): StreamedResponse
    {
        $this->libro->setActiveSheetIndex(0);
        $writer = new Xlsx($this->libro);

        return response()->streamDownload(fn () => $writer->save('php://output'), $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Fila donde empieza la tabla (arriba va el encabezado con el logo). */
    public const FILA_TABLA = 5;

    public const QUETZALES = '"Q" #,##0.00';

    public const FECHA = 'dd/mm/yyyy';

    public const FECHA_HORA = 'dd/mm/yyyy hh:mm';
}
