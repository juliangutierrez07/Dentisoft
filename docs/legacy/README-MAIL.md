# 📧 Sistema de Notificaciones por Correo — DentiSoft 1.0

## 🎯 Descripción

Sistema profesional y robusto de notificaciones por correo electrónico para DentiSoft. Cuando se registra un nuevo paciente, se envía automáticamente un correo de bienvenida con:

- ✅ Diseño elegante y responsive
- ✅ Información del paciente registrado
- ✅ Branding de DentiSoft
- ✅ Manejo robusto de errores
- ✅ Logs detallados
- ✅ Configuración segura con variables de entorno

---

## ⚙️ Instalación

### 1️⃣ Instalar PHPMailer

```bash
# Windows (PowerShell)
composer require phpmailer/phpmailer

# Linux/Mac
composer require phpmailer/phpmailer
```

**O usa el script automático:**

```bash
php setup-mail.php
```

### 2️⃣ Configurar Variables de Entorno

1. **Copia el archivo de ejemplo:**
   ```bash
   cp .env.example .env
   ```

2. **Edita `.env` con tus credenciales:**
   ```env
   MAIL_ENABLED=true
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=tu_email@gmail.com
   MAIL_PASSWORD=tu_app_password
   MAIL_FROM_EMAIL=contacto@tudominio.com
   MAIL_FROM_NAME=DentiSoft Odontología
   ```

### 3️⃣ Probar la Configuración

```bash
php test-mail.php
```

---

## 🔧 Configuración SMTP por Proveedor

### Gmail (Recomendado)

1. **Habilita autenticación 2 factores:**
   https://support.google.com/accounts/answer/185833

2. **Crea una contraseña de aplicación:**
   - Ve a https://myaccount.google.com/apppasswords
   - Selecciona "Mail" y "Windows Computer" (o tu dispositivo)
   - Copia la contraseña generada (16 caracteres)

3. **Configura en `.env`:**
   ```env
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=tu_email@gmail.com
   MAIL_PASSWORD=xxxx xxxx xxxx xxxx
   ```

### Outlook/Hotmail

```env
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=tu_email@outlook.com
MAIL_PASSWORD=tu_contrasena
```

### Hostinger (u otro hosting)

Pide a tu proveedor:
- Host SMTP
- Puerto
- Usuario/email
- Contraseña
- Tipo encriptación (TLS o SSL)

Luego configura en `.env`

### Mailtrap (Para Desarrollo/Pruebas)

1. Registrate en https://mailtrap.io (gratis)
2. Copia las credenciales SMTP de Mailtrap
3. Configura en `.env`:
   ```env
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USERNAME=abc123def456@mailtrap.io
   MAIL_PASSWORD=xyz789abc123
   ```

---

## 📧 Contenido del Correo

### Asunto
```
Bienvenido a DentiSoft Odontología
```

### Contenido incluye:

✅ **Saludo personalizado** al paciente por nombre

✅ **Información de registro:**
- Nombre completo
- Documento de identidad
- Tipo de documento
- Fecha de registro

✅ **Mensaje de seguridad:**
- "Tu historial clínico está protegido"
- "Los doctores accederán de forma segura"

✅ **Próximos pasos:**
1. Completa tu perfil
2. Agenda tu primera cita
3. Carga radiografías anteriores

✅ **Información de contacto de la clínica**

✅ **Diseño responsivo** (funciona en móviles y desktop)

---

## 🛡️ Características de Seguridad

### ✅ Variables de Entorno
- Las credenciales SMTP se almacenan en `.env` (no en código)
- El archivo `.env` NO debe compartirse en repositorios
- Usar `.gitignore` para excluir `.env`

### ✅ Manejo de Errores
- Si el email falla, **NO se pierde el registro del paciente**
- Los errores se registran en logs para auditoría
- Se muestra un warning elegante al usuario
- El flujo continúa normalmente

### ✅ Validaciones
- Se valida el formato de email antes de enviar
- Se verifica que PHPMailer esté disponible
- Se verifican credenciales SMTP antes de enviar
- Se registran todos los intentos de envío

### ✅ Logs Detallados
```
✅ Email sent to paciente@email.com
❌ Email error: [detalles del error]
📧 Mail Config: SMTP=smtp.gmail.com:587
```

---

## 📁 Estructura de Archivos

```
DentiSoft1.0/
├── config/
│   ├── config.php          ← Configuración general
│   ├── database.php        ← Conexión BD
│   ├── mail.php            ← Configuración SMTP
│   ├── env.php             ← Funciones para variables de entorno
│   └── MailService.php     ← Clase para envío de emails
├── modules/
│   └── pacientes/
│       └── crear.php       ← Integración de emails
├── .env                    ← Variables de entorno (NO en git)
├── .env.example            ← Plantilla de ejemplo
├── composer.json           ← Dependencias
├── composer.lock           ← Versiones instaladas
├── vendor/                 ← PHPMailer y dependencias
├── setup-mail.php          ← Script de instalación
├── test-mail.php           ← Script de prueba
└── README-MAIL.md          ← Este archivo
```

---

## 🔄 Flujo de Registro de Paciente

```
Usuario completa el formulario
    ↓
Validaciones en el formulario
    ↓
INSERT en base de datos ✅
    ↓
Obtener ID y datos del paciente
    ↓
Crear instancia de MailService
    ↓
Enviar email de bienvenida
    ├─→ ✅ Email enviado → Alerta positiva
    └─→ ❌ Email falla → Warning elegante + Log
    ↓
Redirigir a lista de pacientes
```

---

## 🧪 Probar Localmente

### Opción 1: Mailtrap (Recomendado para desarrollo)

```bash
# 1. Registrate en https://mailtrap.io
# 2. Copia las credenciales SMTP
# 3. Configura en .env
# 4. Ejecuta test-mail.php
php test-mail.php
```

Todos los correos se capturam en Mailtrap sin realmente enviarse.

### Opción 2: Gmail (Con App Password)

```bash
# Sigue las instrucciones de Gmail arriba
php test-mail.php
```

### Opción 3: Otro proveedor

```bash
# Configura según tu proveedor
php test-mail.php
```

---

## ❌ Solución de Problemas

### ❌ "PHPMailer no está instalado"

**Solución:**
```bash
composer require phpmailer/phpmailer
# O
php setup-mail.php
```

### ❌ "MAIL_ENABLED = false"

**Solución:**
1. Edita `.env`
2. Asegúrate que `MAIL_ENABLED=true`
3. Verifica que `MAIL_USERNAME` no esté vacío

### ❌ "Error de autenticación SMTP"

**Posibles causas:**
- Usuario/password incorrectos
- No usaste App Password en Gmail
- Firewall bloqueando puerto SMTP
- Email o contraseña con espacios

**Solución:**
```bash
# Edita .env y revisa credenciales
# Ejecuta test-mail.php con MAIL_DEBUG=true
MAIL_DEBUG=true
php test-mail.php
```

### ❌ "Timeout SMTP"

**Causas:**
- Servidor SMTP no responde
- Firewall bloqueando conexión
- Puerto SMTP incorrecto

**Solución:**
```bash
# Verifica con telnet
telnet smtp.gmail.com 587

# O prueba otro puerto (465 para SSL)
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
```

### ❌ "Email no llega a bandeja de entrada"

**Posibles causas:**
- Va a spam
- Email no validado en servidor
- Credenciales compartidas / cuenta limitada

**Solución:**
1. Revisa carpeta de spam
2. Whitelist del remitente
3. Usa Mailtrap para pruebas
4. Contacta a tu proveedor de email

### ✅ "Paciente se registra pero email no se envía"

**Esto es correcto.** El sistema está diseñado para:
- ✅ Guardar el paciente SIEMPRE
- ⚠️ Intentar enviar email (pero si falla, NO afecta)
- 📋 Registrar el error en logs

Revisa los logs y la sección de solución de problemas arriba.

---

## 📊 Monitoreo

### Ver logs de email

```bash
# En Windows (PowerShell)
tail -f logs/php_errors.log

# O buscar por palabra clave
grep "📧\|✅\|❌" logs/php_errors.log
```

### Auditoría de registros

La tabla `audit_log` registra:
- Quién registró al paciente
- Cuándo
- IP address
- Datos antes/después

---

## 🚀 Próximas Mejoras

Funcionalidades que se pueden agregar:

- [ ] Enviar email cuando se agenda una cita
- [ ] Recordatorios de citas (24h antes)
- [ ] Confirmación de pago/factura
- [ ] Template variables customizables
- [ ] Historia de envíos por paciente
- [ ] Reenvío manual de emails

---

## 📚 Referencias

- [PHPMailer Documentation](https://github.com/PHPMailer/PHPMailer)
- [Gmail App Passwords](https://support.google.com/accounts/answer/185833)
- [Mailtrap.io](https://mailtrap.io)
- [SMTP Port Reference](https://www.mailboxlayer.com/resources/smtp-ports)

---

## ❓ Soporte

Si encuentras problemas:

1. Revisa los logs: `error_log('...')`
2. Ejecuta: `php test-mail.php`
3. Habilita debug: `MAIL_DEBUG=true` en `.env`
4. Verifica credenciales SMTP
5. Prueba con Mailtrap primero

---

**¡Éxito! Tu sistema de notificaciones está listo.** 🎉
