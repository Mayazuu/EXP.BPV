---
title: "Sistema de Gestión - Bufete Popular"
author: "Mayda Maldonado"
date: "Octubre 2025"
documentclass: article
geometry: margin=2cm
toc: true
---

# Sistema de Gestión - Bufete Popular

## 📋 Descripción del Proyecto

Sistema web integral para la gestión administrativa y operativa del Bufete Popular. Permite el control de estudiantes, asesores, expedientes legales, préstamos de documentos y generación de reportes.

## 🎯 Objetivo

Digitalizar y optimizar los procesos administrativos del Bufete Popular, proporcionando una plataforma centralizada para la gestión de casos, usuarios y recursos.

## 🏗️ Arquitectura del Sistema

### Stack Tecnológico

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 8.2
- **Base de Datos:** MariaDB 11
- **Servidor Web:** Apache 2.4


## 🚀 Módulos del Sistema

### 1. Módulo de Autenticación
- Login seguro con validación de credenciales
- Control de sesiones con timeout automático
- Cambio de contraseña
- Registro de intentos fallidos

### 2. Módulo de Usuarios
- Gestión de usuarios del sistema
- Roles y permisos
- Activación/desactivación de cuentas
- Auditoría de accesos

### 3. Módulo de Estudiantes
- Registro de estudiantes
- Edición de información
- Activación/desactivación
- Historial académico
- Búsqueda y filtrado

### 4. Módulo de Asesores
- Gestión de asesores legales
- Asignación de casos
- Perfil profesional
- Carga de trabajo

### 5. Módulo de Interesados
- Registro de personas que solicitan asesoría
- Datos de contacto
- Motivo de consulta
- Seguimiento inicial

### 6. Módulo de Expedientes
- Creación de expedientes legales
- Vinculación estudiante-asesor-interesado
- Documentación del caso
- Estados del expediente
- Historial de movimientos

### 7. Módulo de Préstamos
- Control de préstamo de documentos
- Registro de salida/devolución
- Estudiantes responsables
- Estados: Prestado/Devuelto
- Alertas de vencimiento

### 8. Módulo de Reportes
- Reportes de expedientes en PDF
- Reportes de préstamos en PDF
- Estadísticas del sistema
- Registro de transacciones
- Inicios de sesión fallidos
- Backup automático semanal de BD

## 📊 Base de Datos

### Tablas Principales

- `usuarios` - Usuarios del sistema
- `estudiantes` - Estudiantes del bufete
- `asesores` - Asesores legales
- `interesados` - Personas que solicitan asesoría
- `expedientes` - Casos legales
- `prestamos` - Control de documentos
- `transacciones` - Auditoría del sistema
- `sesiones_fallidas` - Intentos de acceso fallidos

## 🔐 Seguridad

### Medidas Implementadas

- ✅ Validación de sesiones con tokens
- ✅ Protección contra SQL Injection
- ✅ Protección contra XSS
- ✅ Timeout automático de sesión (15 minutos)
- ✅ Renovación automática de sesión
- ✅ Registro de intentos fallidos
- ✅ Bloqueo temporal después de 3 intentos
- ✅ Contraseñas hasheadas
- ✅ Análisis continuo con Dependency-Track

## 📥 Instalación con DOCKER

### Requisitos Previos

- Docker Desktop instalado
- Docker Compose
- Git
- 4GB RAM mínimo
- 10GB espacio en disco

### Pasos de Instalación
```bash
# 1. Clonar el repositorio
git clone https://github.com/Mayazuu/Proyecto-Practica.git
cd Proyecto-Practica

# 2. Levantar los contenedores
docker-compose up -d

# 3. Verificar que todos los servicios estén corriendo
docker-compose ps

# 4. Acceder a la aplicación
# http://localhost:8080
```

### Configuración Inicial de Base de Datos
```bash
# Importar estructura de base de datos
docker exec -i bufete_db mysql -u user -puser123 bufete_popular < estructura.sql

# O importar con datos de ejemplo
docker exec -i bufete_db mysql -u user -puser123 bufete_popular < bufete_popular.sql
```

## 🔧 Configuración

### Archivo docker-compose.yml

Los servicios se configuran en `docker-compose.yml`:
- Base de datos en puerto 3307
- Aplicación web en puerto 8080
- Jenkins en puerto 8081
- Dependency-Track en puerto 8082

### Conexión a Base de Datos
```php
$host = "db";  // Nombre del contenedor
$user = "user";
$password = "user123";
$database = "bufete_popular";
```

## 🎮 Uso del Sistema

### Acceso al Sistema

**URL:** http://localhost:8080

**Credenciales por defecto:**
- Usuario: `admin`
- Contraseña: `admin123`

⚠️ **Importante:** Cambiar la contraseña después del primer inicio de sesión.

### Flujo de Trabajo Típico

1. **Registro de Interesado**
   - Capturar datos básicos de quien solicita asesoría

2. **Asignación de Asesor**
   - Vincular un asesor legal al caso

3. **Asignación de Estudiante**
   - Designar estudiante responsable del caso

4. **Creación de Expediente**
   - Generar expediente completo con toda la información

5. **Gestión de Documentos**
   - Registrar préstamos de documentos del expediente

6. **Seguimiento**
   - Actualizar estados y generar reportes


## 🛠️ Mantenimiento

### Backup de Base de Datos
```bash
# Backup manual
docker exec bufete_db mysqldump -u user -puser123 bufete_popular > backup_$(date +%Y%m%d).sql

# Backup desde la aplicación
# Reportes → Backup de Base de Datos
```

### Logs del Sistema
```bash
# Ver logs de la aplicación
docker logs -f bufete_web

# Ver logs de la base de datos
docker logs -f bufete_db

# Ver logs de Jenkins
docker logs -f bufete_jenkins
```

### Actualización del Sistema
```bash
# Obtener últimos cambios
git pull origin master

# Reconstruir contenedores
docker-compose up -d --build

# O ejecutar pipeline en Jenkins (recomendado)
```

## 📈 Monitoreo

### Servicios Disponibles

| Servicio | URL | Descripción |
|----------|-----|-------------|
| **Aplicación** | http://localhost:8080 | Sistema principal |
| **Jenkins** | http://localhost:8081 | CI/CD |
| **Dependency-Track** | http://localhost:8082 | Análisis de seguridad |
| **phpMyAdmin** | http://localhost:8888 | Gestión de BD (opcional) |

### Métricas y Reportes

Dentro de la aplicación:
- **Reportes → Ver Transacciones**: Auditoría completa
- **Reportes → Ver Inicios Fallidos**: Intentos de acceso
- **Reportes → Expedientes**: Estado de casos
- **Reportes → Préstamos**: Control de documentos

## 🐛 Troubleshooting

### Problemas Comunes

**1. No puedo acceder a localhost:8080**
```bash
# Verificar que el contenedor esté corriendo
docker ps | findstr bufete_web

# Ver logs
docker logs bufete_web

# Reiniciar contenedor
docker-compose restart web
```

**2. Error de conexión a base de datos**
```bash
# Verificar que la BD esté corriendo
docker ps | findstr bufete_db

# Verificar conexión
docker exec -it bufete_db mysql -u user -puser123 -e "SHOW DATABASES;"
```

**3. Jenkins no arranca**
```bash
# Ver logs
docker logs bufete_jenkins

# Reiniciar
docker restart bufete_jenkins
```

## 👥 Equipo de Desarrollo

- **Desarrollador Principal**: Mayazuu
- **Repositorio**: https://github.com/Mayazuu/Proyecto-Practica

## 📄 Licencia

Este proyecto es parte de un sistema de práctica académica para el Bufete Popular.

## 🤝 Contribuciones

Para contribuir al proyecto:

1. Fork del repositorio
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit de cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request


## 🔄 Changelog

### Versión 1.0.6 (Octubre 2025)
- ✅ Pipeline CI/CD completo con Jenkins
- ✅ Análisis de seguridad con Dependency-Track
- ✅ Generación automática de documentación
- ✅ SBOM automatizado
- ✅ Dockerización completa del sistema

### Versión 1.0.0 (Inicial)
- ✅ Módulos core del sistema
- ✅ Autenticación y autorización
- ✅ Gestión de expedientes
- ✅ Generación de reportes PDF

---

**Desarrollado con ❤️ para el Bufete Popular**