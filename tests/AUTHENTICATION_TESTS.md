# Test Suite - Sistema de Autenticación Dual

## Test 1: OTP Generation
**Objetivo**: Verificar que el endpoint `/api/otp/generate` funciona correctamente

### Comando
```bash
curl -X POST http://localhost:8000/api/otp/generate \
  -H "Content-Type: application/json" \
  -d '{"identifier": "test@example.com"}'
```

**Resultado Esperado**:
- Status: 200
- Response: `{"message": "Código enviado exitosamente.", "expires_at": "..."}`
- Log en Laravel: Código de 6 dígitos

---

## Test 2: OTP Verification (Success)
**Objetivo**: Verificar código correcto

### Comando
```bash
# Primero generar OTP, luego usar el código del log
curl -X POST http://localhost:8000/api/otp/verify \
  -H "Content-Type: application/json" \
  -d '{"identifier": "test@example.com", "code": "123456"}'
```

**Resultado Esperado**:
- Si usuario existe: Login exitoso + redirect
- Si no existe: `requires_registration: true`

---

## Test 3: OTP Verification (Failed)
**Objetivo**: Verificar código incorrecto

### Comando
```bash
curl -X POST http://localhost:8000/api/otp/verify \
  -H "Content-Type: application/json" \
  -d '{"identifier": "test@example.com", "code": "000000"}'
```

**Resultado Esperado**:
- Status: 422
- Error: "Código incorrecto"

---

## Test 4: Rate Limiting - Generation
**Objetivo**: Verificar límite de 3 intentos/hora

### Comandos
```bash
# Intento 1
curl -X POST http://localhost:8000/api/otp/generate -H "Content-Type: application/json" -d '{"identifier": "limit@test.com"}'

# Intento 2
curl -X POST http://localhost:8000/api/otp/generate -H "Content-Type: application/json" -d '{"identifier": "limit@test.com"}'

# Intento 3
curl -X POST http://localhost:8000/api/otp/generate -H "Content-Type: application/json" -d '{"identifier": "limit@test.com"}'

# Intento 4 (debe fallar)
curl -X POST http://localhost:8000/api/otp/generate -H "Content-Type: application/json" -d '{"identifier": "limit@test.com"}'
```

**Resultado Esperado (4to intento)**:
- Status: 422
- Error: "Demasiados intentos. Por favor intenta en X minutos."

---

## Test 5: Rate Limiting - Verification
**Objetivo**: Verificar límite de 5 intentos fallidos

### Comandos
```bash
# Generar OTP primero
curl -X POST http://localhost:8000/api/otp/generate -H "Content-Type: application/json" -d '{"identifier": "verify@test.com"}'

# 5 intentos con código incorrecto
for i in {1..6}; do
  curl -X POST http://localhost:8000/api/otp/verify -H "Content-Type: application/json" -d '{"identifier": "verify@test.com", "code": "999999"}'
done
```

**Resultado Esperado (6to intento)**:
- Status: 422
- Error: "Código bloqueado por seguridad..."

---

## Test 6: OTP Expiration
**Objetivo**: Verificar que códigos expiran en 10 minutos

### Pasos
1. Generar OTP
2. Esperar 11 minutos
3. Intentar verificar

**Resultado Esperado**:
- Error: "Código inválido o expirado"

---

## Test 7: AuthGate - Usuario No Autenticado
**Objetivo**: Verificar interceptor de modal

### Pasos Manuales
1. Ir a http://localhost:8000/auth-demo (sin login)
2. Click en botón "Favoritos"
3. Verificar que modal se abre automáticamente

**Resultado Esperado**:
- Modal abierto
- Tab "Comprador" activo por defecto
- No se ejecuta la acción original

---

## Test 8: AuthGate - Usuario Autenticado
**Objetivo**: Verificar bypass si está logueado

### Pasos Manuales
1. Hacer login primero
2. Ir a http://localhost:8000/auth-demo
3. Click en botón "Favoritos"
4. Verificar que aparece alert con mensaje de éxito

**Resultado Esperado**:
- Modal NO se abre
- Acción se ejecuta inmediatamente
- Alert: "¡Acción ejecutada: Añadir a favoritos!"

---

## Test 9: Password Login (Vendedor)
**Objetivo**: Verificar login tradicional

### Pasos Manuales
1. Abrir modal
2. Click en tab "Vendedor"
3. Ingresar email: admin@conectahn.com
4. Ingresar password: (la configurada)
5. Click "Iniciar Sesión"

**Resultado Esperado**:
- Login exitoso
- Redirect a /app
- Modal se cierra

---

## Test 10: Modal State Management (Zustand)
**Objetivo**: Verificar state global

### Consola del Navegador
```javascript
// Abrir modal
useUIStore.getState().openLoginModal()

// Verificar estado
console.log(useUIStore.getState().isLoginModalOpen) // true

// Cambiar vista
useUIStore.getState().setLoginView('password')
console.log(useUIStore.getState().loginView) // 'password'

// Cerrar modal
useUIStore.getState().closeLoginModal()
console.log(useUIStore.getState().isLoginModalOpen) // false
```

---

## Test 11: Security - Code Hashing
**Objetivo**: Verificar que códigos se guardan hasheados

### Query SQL
```sql
SELECT identifier, code, expires_at 
FROM one_time_passwords 
ORDER BY created_at DESC 
LIMIT 5;
```

**Resultado Esperado**:
- Columna `code` debe mostrar hash BCrypt (empezando con `$2y$`)
- NO debe mostrar el código en texto plano

---

## Test 12: Responsive Design
**Objetivo**: Verificar modal en diferentes pantallas

### Pasos
1. Desktop (1920x1080): Modal centrado
2. Tablet (768x1024): Modal ajustado
3. Mobile (375x667): Modal full-width

**Resultado Esperado**:
- Modal siempre legible
- Inputs accesibles
- Botones táctiles (≥ 44px altura)

---

## Checklist de Validaciones

### Backend
- [ ] OTP generado correctamente
- [ ] Código hasheado en DB
- [ ] Expiración de 10 minutos
- [ ] Rate limit 3/hora (generación)
- [ ] Rate limit 5 max (verificación)
- [ ] IP tracking funciona
- [ ] User agent se almacena
- [ ] OTP se elimina después de verificación exitosa

### Frontend
- [ ] Modal se abre via Zustand
- [ ] Tabs cambian correctamente
- [ ] Formulario OTP envía request
- [ ] Formulario Password envía request
- [ ] Errores se muestran inline
- [ ] Loading states aparecen
- [ ] Modal se cierra después de login
- [ ] AuthGate intercepta correctamente

### UX
- [ ] Sin recargas de página
- [ ] Animaciones suaves
- [ ] Mensajes de error claros
- [ ] Feedback visual inmediato
- [ ] Accesibilidad (teclado, screen readers)

### Seguridad
- [ ] Códigos nunca en plain text
- [ ] Rate limiting previene abuse
- [ ] CSRF protection activa
- [ ] Validación de input sanitizada
- [ ] SQL injection prevenida (Eloquent)
