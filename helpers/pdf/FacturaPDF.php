<?php
/**
 * DentiSoft 1.0 - Factura PDF Generator
 * Generador profesional de facturas en PDF para el Portal del Paciente
 * FASE 3: Descarga de facturas en PDF
 */

require_once __DIR__ . '/../../vendor/tcpdf/tcpdf.php';

class FacturaPDF extends TCPDF
{
    private $clinicaNombre;
    private $clinicaDireccion;
    private $clinicaTelefono;
    private $clinicaEmail;
    private $clinicaNIT;

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskfile = false, $pdfversion = '1.7')
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskfile, $pdfversion);
        
        // Configuración de la clínica (puedes obtener estos valores de config o base de datos)
        $this->clinicaNombre = defined('CLINICA_NOMBRE') ? CLINICA_NOMBRE : 'DentiSoft Clínica Dental';
        $this->clinicaDireccion = defined('CLINICA_DIRECCION') ? CLINICA_DIRECCION : 'Dirección de la clínica';
        $this->clinicaTelefono = defined('CLINICA_TELEFONO') ? CLINICA_TELEFONO : '+57 300 123 4567';
        $this->clinicaEmail = defined('CLINICA_EMAIL') ? CLINICA_EMAIL : 'contacto@dentisoft.com';
        $this->clinicaNIT = defined('CLINICA_NIT') ? CLINICA_NIT : '900.123.456-7';
    }

    // Header personalizado
    public function Header()
    {
        // Logo DentiSoft
        $logoPath = __DIR__ . '/../../assets/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 35, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        // Información de la clínica
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 8, $this->clinicaNombre, 0, 1, 'R');
        
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, $this->clinicaDireccion, 0, 1, 'R');
        $this->Cell(0, 5, 'Tel: ' . $this->clinicaTelefono, 0, 1, 'R');
        $this->Cell(0, 5, 'Email: ' . $this->clinicaEmail, 0, 1, 'R');
        $this->Cell(0, 5, 'NIT: ' . $this->clinicaNIT, 0, 1, 'R');

        // Línea separadora
        $this->SetLineStyle(array('width' => 0.5, 'color' => array(0, 102, 204)));
        $this->Line(15, 45, 195, 45);
        
        // Título de la factura
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 15, 'FACTURA ELECTRÓNICA', 0, 1, 'C');
        
        $this->SetY(50);
    }

    // Footer personalizado
    public function Footer()
    {
        // Posición a 15mm del fondo
        $this->SetY(-25);
        
        // Línea separadora
        $this->SetLineStyle(array('width' => 0.3, 'color' => array(150, 150, 150)));
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        // Información del pie de página
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        
        $this->Cell(0, 5, $this->clinicaNombre . ' - ' . $this->clinicaDireccion, 0, 1, 'C');
        $this->Cell(0, 5, 'Tel: ' . $this->clinicaTelefono . ' | Email: ' . $this->clinicaEmail, 0, 1, 'C');
        $this->Cell(0, 5, 'NIT: ' . $this->clinicaNIT, 0, 1, 'C');
        
        // Número de página
        $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 1, 'C');
    }

    /**
     * Generar factura completa
     * 
     * @param array $factura Datos de la factura
     * @param array $paciente Datos del paciente
     * @param array $detalles Detalles de la factura (servicios)
     * @param array $odontologo Datos del odontólogo
     * @return string Contenido del PDF
     */
    public function generarFactura($factura, $paciente, $detalles, $odontologo)
    {
        // Configuración inicial del documento
        $this->SetCreator('DentiSoft - Sistema de Gestión Odontológica');
        $this->SetAuthor('DentiSoft');
        $this->SetTitle('Factura #' . $factura['numero_factura']);
        $this->SetSubject('Factura Electrónica');
        $this->SetKeywords('Factura, DentiSoft, Odontología');
        
        // Márgenes
        $this->SetMargins(15, 55, 15);
        $this->SetAutoPageBreak(true, 30);
        
        // Agregar página
        $this->AddPage();
        
        // Información de la factura
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(40, 6, 'Número de Factura:', 0, 0, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(60, 6, $factura['numero_factura'], 0, 0, 'L');
        
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(30, 6, 'Fecha Emisión:', 0, 0, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, date('d/m/Y', strtotime($factura['fecha_emision'])), 0, 1, 'L');
        
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(40, 6, 'Estado:', 0, 0, 'L');
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor($this->getEstadoColor($factura['estado']));
        $this->Cell(60, 6, strtoupper($factura['estado']), 0, 0, 'L');
        
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(30, 6, 'Vencimiento:', 0, 0, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, $factura['fecha_vencimiento'] ? date('d/m/Y', strtotime($factura['fecha_vencimiento'])) : 'N/A', 0, 1, 'L');
        
        // Espacio
        $this->Ln(8);
        
        // Información del paciente
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 8, 'INFORMACIÓN DEL PACIENTE', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(40, 6, 'Nombre:', 0, 0, 'L');
        $this->Cell(0, 6, $paciente['nombre'] . ' ' . $paciente['apellido'], 0, 1, 'L');
        
        $this->Cell(40, 6, 'Documento:', 0, 0, 'L');
        $this->Cell(0, 6, $paciente['documento'], 0, 1, 'L');
        
        if (!empty($paciente['telefono'])) {
            $this->Cell(40, 6, 'Teléfono:', 0, 0, 'L');
            $this->Cell(0, 6, $paciente['telefono'], 0, 1, 'L');
        }
        
        if (!empty($paciente['email'])) {
            $this->Cell(40, 6, 'Email:', 0, 0, 'L');
            $this->Cell(0, 6, $paciente['email'], 0, 1, 'L');
        }
        
        // Espacio
        $this->Ln(8);
        
        // Información del odontólogo
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 8, 'PROFESIONAL TRATANTE', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'Dr(a). ' . $odontologo['nombre'] . ' ' . $odontologo['apellido'], 0, 1, 'L');
        
        // Espacio
        $this->Ln(8);
        
        // Tabla de detalles
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 8, 'DETALLE DE SERVICIOS', 0, 1, 'L');
        
        // Encabezados de tabla
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor(0, 102, 204);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(90, 7, 'Descripción', 1, 0, 'L', true);
        $this->Cell(30, 7, 'Cantidad', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Valor Unit.', 1, 0, 'R', true);
        $this->Cell(30, 7, 'Total', 1, 1, 'R', true);
        
        // Filas de detalles
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(245, 245, 245);
        
        if (!empty($detalles)) {
            foreach ($detalles as $detalle) {
                $this->Cell(90, 6, $detalle['descripcion'], 1, 0, 'L', false);
                $this->Cell(30, 6, $detalle['cantidad'], 1, 0, 'C', false);
                $this->Cell(30, 6, '$' . number_format($detalle['valor_unitario'], 0, ',', '.'), 1, 0, 'R', false);
                $this->Cell(30, 6, '$' . number_format($detalle['subtotal'], 0, ',', '.'), 1, 1, 'R', false);
            }
        } else {
            // Si no hay detalles, mostrar un mensaje
            $this->Cell(180, 6, 'Servicios generales del tratamiento', 1, 1, 'L', false);
        }
        
        // Espacio
        $this->Ln(4);
        
        // Totales
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $this->Cell(120, 6, '', 0, 0, 'L');
        $this->Cell(30, 6, 'Subtotal:', 0, 0, 'R');
        $this->Cell(30, 6, '$' . number_format($factura['subtotal'], 0, ',', '.'), 0, 1, 'R');
        
        if ($factura['descuento'] > 0) {
            $this->Cell(120, 6, '', 0, 0, 'L');
            $this->Cell(30, 6, 'Descuento:', 0, 0, 'R');
            $this->Cell(30, 6, '-$' . number_format($factura['descuento'], 0, ',', '.'), 0, 1, 'R');
        }
        
        if ($factura['iva'] > 0) {
            $this->Cell(120, 6, '', 0, 0, 'L');
            $this->Cell(30, 6, 'IVA:', 0, 0, 'R');
            $this->Cell(30, 6, '$' . number_format($factura['iva'], 0, ',', '.'), 0, 1, 'R');
        }
        
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(120, 8, '', 0, 0, 'L');
        $this->Cell(30, 8, 'TOTAL:', 0, 0, 'R');
        $this->Cell(30, 8, '$' . number_format($factura['total'], 0, ',', '.'), 0, 1, 'R');
        
        // Estado de pago
        $this->Ln(4);
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(120, 6, '', 0, 0, 'L');
        $this->Cell(30, 6, 'Pagado:', 0, 0, 'R');
        $this->Cell(30, 6, '$' . number_format($factura['total_pagado'], 0, ',', '.'), 0, 1, 'R');
        
        $this->Cell(120, 6, '', 0, 0, 'L');
        $this->Cell(30, 6, 'Saldo Pendiente:', 0, 0, 'R');
        $this->Cell(30, 6, '$' . number_format($factura['saldo_pendiente'], 0, ',', '.'), 0, 1, 'R');
        
        // Observaciones
        if (!empty($factura['notas'])) {
            $this->Ln(8);
            $this->SetFont('helvetica', 'B', 10);
            $this->SetTextColor(0, 51, 102);
            $this->Cell(0, 6, 'OBSERVACIONES:', 0, 1, 'L');
            
            $this->SetFont('helvetica', '', 9);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell(0, 5, $factura['notas'], 0, 'L');
        }
        
        // Código único de factura
        $this->Ln(8);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'Código único de factura: ' . md5($factura['id'] . $factura['numero_factura'] . $factura['fecha_emision']), 0, 1, 'C');
        
        // Generar el PDF
        return $this->Output('factura_' . $factura['numero_factura'] . '.pdf', 'S');
    }
    
    /**
     * Obtener color según estado de factura
     */
    private function getEstadoColor($estado)
    {
        return match ($estado) {
            'pagada' => array(0, 153, 76),
            'pendiente' => array(255, 153, 0),
            'parcial' => array(0, 102, 204),
            'vencida' => array(204, 0, 0),
            'anulada' => array(128, 128, 128),
            default => array(0, 0, 0),
        };
    }
}
