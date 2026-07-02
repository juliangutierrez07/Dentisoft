# 🚀 Guía de Inicio Rápido — Sistema de Notificaciones por Correo

## ⚡ Resumen de Cambios

Se ha implementado un sistema **completo, robusto y profesional** de notificaciones por correo para DentiSoft. Cuando se registra un nuevo paciente, se envía automáticamente un correo de bienvenida elegante y responsivo.

---

## 📋 Archivos Creados/Modificados

### ✅ Archivos Nuevos
```
config/
├── mail.php                    ← Configuración SMTP
├── env.php                     ← Funciones para variables de entorno
└── MailService.php             ← Clase para envío de emails

includes/
└── email-warning.php           ← Toast de warning elegante

composer.json                   ← Dependencias PHP
.env.example                    ← Plantilla de configuración
.gitignore                      ← Seguridad (excluir .env)
setup-mail.php                  ← Script de instalación
test-mail.php                   ← Script de prueba
README-MAIL.md                  ← Documentación completa
QUICKSTART-MAIL.md              ← Esta guía
```

### ✏️ Archivos Modificados
```
modules/pacientes/crear.php     ← Integración de envío de emails
includes/header.php             ← Integración de componente warning
```

---

## 🎯 Pasos para Activar (Rápido)

### 1️⃣ Instalar PHPMailer (2 minutos)

**Opción A: Script automático**
```bash
php setup-mail.php
```

**Opción B: Manual con Composer**
```bash
composer require phpmailer/phpmailer
```

### 2️⃣ Configurar SMTP (5 minutos)

**Paso 1:** Copia el archivo de ejemplo
```bash
cp .env.example .env
```

**Paso 2:** Edita `.env` con tus credenciales

**Para Gmail:**
```env
MAIL_ENABLED=true
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_app_password
MAIL_FROM_EMAIL=tu_email@gmail.com
MAIL_FROM_NAME=DentiSoft Odontología
```

**Para Outlook:**
```env
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=tu_email@outlook.com
MAIL_PASSWORD=tu_contrasena
```

**Para otro proveedor:**
- Contacta a tu proveedor de hosting
- Solicita credenciales SMTP
- Completa los valores en `.env`

### 3️⃣ Prueba (2 minutos)

```bash
php test-mail.php
```

Sigue las instrucciones. Ingresa tu email y recibirás un correo de prueba.

---

## ✅ Verificación Rápida

Si después de registrar un paciente ves:

✅ **Mensaje "Paciente registrado y correo enviado exitosamente"**
→ ¡Todo funciona perfecto!

⚠️ **Toast naranja en la esquina inferior derecha**
→ El paciente se registró pero el email falló (revisa los logs)

❌ **No ves nada de email**
→ Probablemente no configuraste `.env` (ejecuta `test-mail.php`)

---

## 🔐 Seguridad Importante

### ⚠️ NO Olvides:

1. **Crear `.env` desde `.env.example`**
   ```bash
   cp .env.example .env
   ```

2. **NO Compartir `.env` en Git**
   - Ya está en `.gitignore`
   - Pero verifica que NO lo agregues manualmente

3. **Usar App Password en Gmail**
   - NO uses contraseña normal
   - Ve a: https://support.google.com/accounts/answer/185833

4. **Mantener secretas las credenciales**
   - No escribas email/password en código
   - Usa SIEMPRE el archivo `.env`

---

## 📊 ¿Qué sucede cuando se registra un paciente?

```
Usuario completa formulario
    ↓
✅ Paciente guardado en BD
    ↓
📧 Intenta enviar email
    ├─→ ✅ Email enviado → Mensaje positivo
    └─→ ❌ Email falló → Warning discreto + Log
    ↓
✅ Redirigir a lista (SIN importar si email funcionó)
```

**Nota importante:** Si el email falla, **NO se pierde el registro**. El paciente se guarda de todas formas.

---

## 🧪 Modo Desarrollo (Recomendado)

Para pruebas sin enviar reales, usa **Mailtrap.io**:

1. Registrate gratis en https://mailtrap.io
2. Copia credenciales SMTP
3. Pega en `.env`:
   ```env
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USERNAME=abc123def456@mailtrap.io
   MAIL_PASSWORD=xyz789abc123
   ```
4. Todos los emails se capturan en Mailtrap

---

## 📧 Contenido del Correo

Cada correo incluye:

✅ Saludo personalizado
✅ Confirmación de registro
✅ Datos del paciente
✅ Información de seguridad
✅ Próximos pasos
✅ Información de contacto
✅ Diseño responsive
✅ Branding DentiSoft

---

## ❌ Solución Rápida de Problemas

| Problema | Solución |
|----------|----------|
| "PHPMailer not found" | `composer require phpmailer/phpmailer` |
| "No existe .env" | `cp .env.example .env` |
| "Falla autenticación" | Revisa usuario/password en .env |
| "No recibo email" | Ejecuta `php test-mail.php` |
| "¿Revisar logs?" | `error_log()` escribe en logs del servidor |
| "Gmail dice no permitido" | Usa App Password, no contraseña normal |

---

## 📚 Documentación Completa

Para más detalles, consulta:
```
README-MAIL.md
```

Contiene:
- Instalación detallada
- Configuración por proveedor
- Troubleshooting exhaustivo
- Referencias
- Próximas mejoras

---

## 🎉 ¡Listo!

Tu sistema de notificaciones está activo. Cuando registres un paciente:

```
✅ Paciente creado
✅ Correo de bienvenida enviado
✅ Log registrado
✅ Usuario ve mensaje de éxito
```

**¡Felicidades!** 🚀
