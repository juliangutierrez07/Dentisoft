<?php
/**
 * Portal del Paciente - Descargar Factura PDF
 * FASE 3: Generación y descarga de facturas en PDF
 * 
 * Este endpoint genera y descarga una factura en PDF con validaciones de seguridad
 * para asegurar que cada paciente solo pueda descargar sus propias facturas.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/patient_portal.php';
require_once __DIR__ . '/../../helpers/pdf/FacturaPDF.php';

// Validar que el paciente esté autenticado y haya cambiado su contraseña
requirePatientPasswordReady();

$pacienteSesion = currentPatient();

// Validar ID de factura
$facturaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($facturaId <= 0) {
    error_log('Portal paciente: Intento de descargar factura con ID inválido - Paciente ID: ' . $pacienteSesion['id']);
    header('HTTP/1.0 400 Bad Request');
    exit('ID de factura inválido.');
}

try {
    $db = getDB();
    
    // Validar que la factura pertenezca al paciente autenticado
    $stmt = $db->prepare("
        SELECT
            f.id,
            f.numero_factura,
            f.fecha_emision,
            f.fecha_vencimiento,
            f.subtotal,
            f.descuento,
            f.iva,
            f.total,
            f.total_pagado,
            f.saldo_pendiente,
            f.estado,
            f.notas,
            f.plan_id,
            p.id AS paciente_id,
            p.nombre AS paciente_nombre,
            p.apellido AS paciente_apellido,
            p.documento AS paciente_documento,
            p.telefono AS paciente_telefono,
            p.email AS paciente_email,
            u.nombre AS odontologo_nombre,
            u.apellido AS odontologo_apellido
        FROM facturas f
        INNER JOIN pacientes p ON p.id = f.paciente_id
        INNER JOIN paciente_accesos pa ON pa.paciente_id = p.id
        INNER JOIN usuarios u ON u.id = f.odontologo_id
        WHERE f.id = :factura_id
            AND pa.id = :acceso_id
            AND pa.paciente_id = :paciente_id
        LIMIT 1
    ");
    
    $stmt->execute([
        ':factura_id' => $facturaId,
        ':acceso_id' => $pacienteSesion['acceso_id'],
        ':paciente_id' => $pacienteSesion['id'],
    ]);
    
    $factura = $stmt->fetch();
    
    if (!$factura) {
        // La factura no existe o no pertenece al paciente
        error_log('Portal paciente: Intento de acceso no autorizado a factura ID: ' . $facturaId . ' - Paciente ID: ' . $pacienteSesion['id']);
        header('HTTP/1.0 403 Forbidden');
        exit('No tienes permiso para acceder a esta factura.');
    }
    
    // Obtener detalles de la factura
    $stmtDetalles = $db->prepare("
        SELECT
            df.descripcion,
            df.cantidad,
            df.valor_unitario,
            df.subtotal
        FROM detalle_facturas df
        WHERE df.factura_id = :factura_id
        ORDER BY df.id ASC
    ");
    
    $stmtDetalles->execute([':factura_id' => $facturaId]);
    $detalles = $stmtDetalles->fetchAll();
    
    // Preparar datos del paciente
    $paciente = [
        'nombre' => $factura['paciente_nombre'],
        'apellido' => $factura['paciente_apellido'],
        'documento' => $factura['paciente_documento'],
        'telefono' => $factura['paciente_telefono'],
        'email' => $factura['paciente_email'],
    ];
    
    // Preparar datos del odontólogo
    $odontologo = [
        'nombre' => $factura['odontologo_nombre'],
        'apellido' => $factura['odontologo_apellido'],
    ];
    
    // Preparar datos de la factura
    $datosFactura = [
        'id' => $factura['id'],
        'numero_factura' => $factura['numero_factura'],
        'fecha_emision' => $factura['fecha_emision'],
        'fecha_vencimiento' => $factura['fecha_vencimiento'],
        'subtotal' => $factura['subtotal'],
        'descuento' => $factura['descuento'],
        'iva' => $factura['iva'],
        'total' => $factura['total'],
        'total_pagado' => $factura['total_pagado'],
        'saldo_pendiente' => $factura['saldo_pendiente'],
        'estado' => $factura['estado'],
        'notas' => $factura['notas'],
    ];
    
    // Generar PDF
    $pdf = new FacturaPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdfContent = $pdf->generarFactura($datosFactura, $paciente, $detalles, $odontologo);
    
    // Enviar el PDF al navegador
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="factura_' . $factura['numero_factura'] . '.pdf"');
    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    echo $pdfContent;
    exit;
    
} catch (PDOException $e) {
    error_log('Portal paciente descargar factura error: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Error al generar la factura. Por favor, intenta nuevamente.');
} catch (Exception $e) {
    error_log('Portal paciente descargar factura exception: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Error al generar la factura. Por favor, intenta nuevamente.');
}
