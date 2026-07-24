# Contexto del Proyecto — Módulo KPI de Infraestructura

## Quién soy

Felipe Henríquez, Infrastructure Lead en Unifrutti LATAM (fusión Verfrut + Unifrutti).
Gestiono infraestructura para Chile y Perú: 500+ equipos, plantas, campos, datacenter on-premise.

---

## La plataforma existente

Tengo una plataforma web desarrollada en **Laravel** que ya tiene integraciones con:
- **Active Directory** (verfrut.cl)
- **Microsoft Entra ID** (Azure AD)
- **GLPI** (helpdesk e inventario de activos)
- Administración de líneas telefónicas
- Consumos e informes de líneas telefónicas

Sobre esta misma plataforma quiero desarrollar un módulo nuevo para gestionar y demostrar el cumplimiento de mis KPIs de evaluación anual.

---

## Contexto de evaluación

Soy evaluado anualmente con 4 KPIs individuales que suman 50% de mi evaluación.
El período de evaluación es **enero a diciembre de 2027**.
Cada KPI tiene escala del 1 al 5 (1=significativamente por debajo, 3=meta, 5=significativamente por encima).

---

## KPIs definidos

### ✅ KPI 1 — Disponibilidad de servicios críticos (ACTIVO — desarrollar ahora)
**Peso:** 15%
**Meta (nivel 3):** Mantener durante 2027 una disponibilidad mínima de **99,5%** en los servicios críticos de infraestructura bajo administración, excluyendo mantenimientos programados, con monitoreo y reporte mensual.

| Nivel | Descripción |
|-------|-------------|
| 1 | < 98,5% |
| 2 | 99,0% |
| 3 (meta) | 99,5% |
| 4 | 99,7% |
| 5 | ≥ 99,9% |

**Stack de monitoreo disponible:** CheckMK (API REST disponible), Zabbix, Grafana.
**Fuente de datos principal para este módulo:** API REST de CheckMK.

**Lo que se necesita desarrollar:**
- Conexión a la API REST de CheckMK para obtener disponibilidad de hosts y servicios
- Dashboard con disponibilidad por servicio/host, vista mensual y anual
- Gráficos de uptime/downtime claros y presentables
- Cálculo automático de % de disponibilidad excluyendo mantenimientos programados (downtimes)
- Informe exportable (PDF o tabla) con el resumen mensual y acumulado anual
- Indicador visual de en qué nivel del KPI se encuentra (1 al 5)

---

### ⏳ KPI 2 — Continuidad operacional / Respaldos (PENDIENTE — próxima fase)
**Peso:** 15%
**Meta (nivel 3):** Asegurar la continuidad operacional mediante la ejecución de 100% de los respaldos programados y al menos 4 pruebas de restauración o recuperación durante 2027, con resultado satisfactorio mínimo de 95%.

| Nivel | Descripción |
|-------|-------------|
| 1 | < 85% / 1 prueba |
| 2 | 90% / 2 pruebas |
| 3 (meta) | 95% / 4 pruebas |
| 4 | 98% / 6 pruebas |
| 5 | 100% / ≥ 8 pruebas |

**Lo que se necesitará desarrollar (pendiente):**
- Registro de respaldos programados y su estado (exitoso/fallido)
- Registro de pruebas de restauración con fecha, resultado y porcentaje de éxito
- Dashboard de cumplimiento de respaldos y pruebas
- Alerta cuando se acerca fecha de prueba trimestral

---

### ⏳ KPI 3 — Documentación técnica (PENDIENTE — próxima fase)
**Peso:** 10%
**Meta (nivel 3):** Actualizar al cierre de 2027 al menos el 90% de la documentación técnica, inventario de infraestructura, licencias, capacidades y procedimientos críticos, identificando y gestionando oportunamente renovaciones y riesgos.

| Nivel | Descripción |
|-------|-------------|
| 1 | < 70% |
| 2 | 80% |
| 3 (meta) | 90% |
| 4 | 95% |
| 5 | 100% |

**Lo que se necesitará desarrollar (pendiente):**
- Listado de documentos técnicos requeridos con estado (pendiente/completo/desactualizado)
- Porcentaje de completitud de documentación
- Alertas de vencimiento de licencias y renovaciones
- Integración con GLPI para inventario de activos (ya conectado)

---

### ⏳ KPI 4 — Formación y certificación (PENDIENTE — próxima fase)
**Peso:** 10%
**Meta (nivel 3):** Completar durante 2027 una formación o certificación en infraestructura, nube, virtualización, redes o continuidad operacional, aplicando al menos una mejora documentada.

| Nivel | Descripción |
|-------|-------------|
| 1 | No iniciado |
| 2 | Inscrito / < 50% |
| 3 (meta) | Curso aprobado + 1 mejora documentada |
| 4 | Aprobado ≥ 85% + 2 mejoras |
| 5 | Certificación obtenida + 3 mejoras |

**Lo que se necesitará desarrollar (pendiente):**
- Registro de cursos/certificaciones con estado y progreso
- Registro de mejoras documentadas asociadas a la formación
- Indicador visual del nivel alcanzado

---

## Instrucciones para el desarrollo

### Por dónde empezar
**Desarrollar primero KPI 1** — módulo de disponibilidad conectado a CheckMK.

### CheckMK — API REST
- CheckMK tiene API REST disponible en: `https://<servidor>//<site>/check_mk/api/1.0/`
- Autenticación: Bearer token (usuario de automatización en CheckMK)
- Endpoints relevantes:
  - `GET /objects/host/{host_name}` — info de un host
  - `GET /domain-types/host/collections/all` — todos los hosts
  - `GET /domain-types/service/collections/all` — todos los servicios
  - `POST /domain-types/report/actions/instant_report/invoke` — reportes (si está habilitado)
  - Para disponibilidad histórica usar: endpoint de availability o la API de Livestatus vía REST

**Nota importante:** CheckMK tiene dos APIs — la REST API moderna y Livestatus (más potente para queries de disponibilidad histórica). Para disponibilidad por períodos se recomienda explorar el endpoint de availability o Livestatus queries via REST.

### Stack de la plataforma
- **Backend:** Laravel (PHP)
- **Frontend:** (confirmar con el proyecto — Blade, Vue, React o similar)
- **Base de datos:** (confirmar con el proyecto)
- **Integraciones existentes:** AD, Entra ID, GLPI

### Lo que debe entregar el módulo KPI 1
1. **Configuración:** definir qué hosts/servicios son "críticos" y se incluyen en el cálculo
2. **Exclusión de mantenimientos:** los downtimes programados en CheckMK no cuentan como indisponibilidad
3. **Vista mensual:** % de disponibilidad por servicio y total del mes seleccionado
4. **Vista anual:** % acumulado 2027 con gráfico de evolución mes a mes
5. **Indicador de nivel KPI:** mostrar claramente en qué nivel (1–5) está el resultado actual
6. **Informe exportable:** PDF o Excel con el resumen para presentar a jefatura (Erick Olguín)

---

## Contexto adicional relevante

- **Jefatura directa:** Erick Olguín (NLATAM Infrastructure Manager) — a quien se le presentarán los informes
- **Período:** enero–diciembre 2027
- **Filosofía de desarrollo:** preferir soluciones nativas y simples sobre complejidad innecesaria
- **Los KPI 2, 3 y 4 se desarrollarán en fases posteriores** dentro de la misma plataforma Laravel
