# 6. Relación con los módulos del ciclo

El desarrollo del proyecto Web Agencia de Viajes guarda una relación directa con los módulos del ciclo formativo de Administración de Sistemas Informáticos en Red, ya que en él se aplican de forma práctica los conocimientos adquiridos a lo largo del ciclo.

---

## Administración de Sistemas Operativos

Este módulo se relaciona con las siguientes tareas del proyecto:

- Instalación y configuración del sistema operativo del servidor donde se alojará la aplicación web.
- Gestión del servidor web Apache, incluyendo su instalación, configuración de servicios y puesta en marcha.
- Administración de usuarios y permisos a nivel de sistema para asegurar el correcto funcionamiento del servidor.
- Uso de Docker para la virtualización del entorno de desarrollo y pruebas.
- Monitorización básica del sistema para garantizar la estabilidad y disponibilidad del servicio.

---

## Administración de Sistemas Gestores de Bases de Datos

Este módulo se aplica directamente en las tareas relacionadas con la gestión de la información del sistema:

- Diseño del modelo de la base de datos.
- Instalación y configuración del SGBD MySQL 8.
- Creación de tablas, relaciones, índices y consultas SQL.
- Gestión de usuarios de base de datos y asignación de permisos.
- Creación de triggers.
- Optimización del rendimiento y realización de copias de seguridad de la base de datos.

---

## Planificación y Administración de Redes

Los conocimientos adquiridos en este módulo se aplican en los aspectos relacionados con la conectividad y los servicios de red:

- Configuración básica de la red para permitir el acceso a la aplicación web.
- Uso de protocolos de red como HTTP y HTTPS.
- Comprensión del funcionamiento cliente-servidor.
- Gestión básica de direccionamiento IP y servicios de red necesarios.
- Tabla login_attempts guarda las direcciones IP.

---

## Lenguajes de Marcas

Este módulo es fundamental para el desarrollo del proyecto, ya que proporciona las bases del diseño y estructuración de la aplicación web:

- Uso de HTML5 para estructurar correctamente los contenidos de la página web.
- Aplicación de CSS3 para el diseño visual, maquetación y diseño responsive.
- Organización semántica del contenido para mejorar la accesibilidad y usabilidad.
- Validación del código para cumplir estándares web.

---

## Implantación de Sistemas Operativos

Este módulo se relaciona directamente con la preparación y configuración del entorno donde se desplegará la aplicación web:

- Instalación y configuración del sistema operativo del servidor que alojará la aplicación.
- Gestión básica del sistema (usuarios, permisos, procesos y servicios).
- Configuración del entorno necesario para el servidor web Apache y el lenguaje backend.
- Uso de máquinas virtuales o contenedores para el entorno de desarrollo y pruebas.

---

## Digitalización Aplicada a los Sectores Productivos GS

Este módulo se relaciona con el proyecto en el proceso de transformación digital de una agencia de viajes tradicional hacia un entorno web:

- Análisis del impacto de la digitalización en el sector turístico.
- Desarrollo de una solución digital que sustituye procesos manuales por automatizados.
- Uso de herramientas digitales para la gestión de reservas, clientes y ofertas.
- Mejora de la eficiencia y competitividad de la "empresa" mediante el uso de tecnologías web.
- Adaptación del proyecto a las necesidades reales del entorno empresarial actual.

---

## Fundamentos de Hardware

Este módulo se aplica en los aspectos relacionados con la infraestructura física necesaria para el funcionamiento del sistema:

- Conocimiento de los componentes hardware de un servidor (CPU, RAM, almacenamiento).
- Selección de recursos hardware adecuados según las necesidades del proyecto.
- Relación entre rendimiento del sistema y capacidad del hardware.
- Identificación de posibles limitaciones físicas que afecten al servicio.

---

## Sostenibilidad Aplicada al Sistema Productivo

Este módulo se relaciona con el proyecto desde el punto de vista de la eficiencia y el uso responsable de los recursos tecnológicos:

- Optimización del uso de recursos hardware y software.
- Uso de tecnologías que reduzcan el consumo innecesario de recursos.
- Diseño de una aplicación escalable y mantenible que evite rehacer sistemas completos en el futuro.

---

## Implantación de Aplicaciones Web

Es uno de los módulos que mas está relacionado directamente con el proyecto:

- Desarrollo del frontend de la aplicación utilizando HTML5, CSS3 y JavaScript.
- Desarrollo del backend para la gestión de usuarios, reservas, destinos y panel de administración.
- Integración de funcionalidades como formularios, validaciones y comunicación con la base de datos.
- Uso de control de versiones con Git para el seguimiento del proyecto.

---

## Optativa: Programación

Este módulo se aplica en el proyecto a través del desarrollo de una herramienta de envío paralelo de recordatorios de viaje (`tools/recordatorios.php`):

- Implementación de concurrencia mediante `pcntl_fork()`, lanzando múltiples procesos hijo que se ejecutan en paralelo, cada uno con su propia conexión a la base de datos.
- El proceso padre reparte la lista de destinatarios en bloques y espera a que todos los hijos terminen con `pcntl_wait()`, recogiendo los resultados parciales de cada proceso.
- Uso de variables de entorno y argumentos por línea de comandos (`--days`, `--workers`, `--send`) para controlar el comportamiento del script.
- Aplicación de estructuras de control, funciones y manejo de errores para garantizar la robustez del proceso en caso de fallo de algún hijo.

---

## Seguridad y Alta Disponibilidad

Este módulo se refleja en las tareas relacionadas con la protección del sistema y la fiabilidad del servicio:

- Implementación de cifrado de contraseñas mediante bcrypt y sistemas de autenticación segura.
- Protección frente a ataques comunes: SQL Injection mediante PDO con sentencias preparadas, XSS mediante htmlspecialchars() y cabeceras CSP, CSRF mediante tokens de sesión validados en todos los formularios.
- Control de accesos y permisos para el panel de administración, restringido a usuarios con rol de administrador.
- Implementación de una pasarela de pago simulada con validación de datos de tarjeta en servidor, demostrando el punto de integración segura con servicios de pago externos.
- Aplicación de medidas básicas de alta disponibilidad mediante Docker con health checks, garantizando un tiempo mínimo de servicio del 95%.
- Aplicación de cabeceras de seguridad HTTP (X-Frame-Options, X-Content-Type-Options, Referrer-Policy) configuradas en el servidor Apache.
- Implantación de técnicas seguras de acceso remoto: en un despliegue real en servidor Linux, el acceso se realizaría exclusivamente mediante SSH con autenticación por clave pública (deshabilitando el acceso por contraseña), cambio del puerto por defecto, restricción de acceso al usuario root y uso de `fail2ban` para bloquear intentos de fuerza bruta. El acceso a los contenedores en producción se haría únicamente desde localhost mediante `docker exec`, nunca exponiendo puertos de administración al exterior.

---

## Servicios de Red e Internet

Este módulo se relaciona con la configuración y funcionamiento de los servicios en red:

- Configuración de los servicios web y de red necesarios para el acceso a la aplicación.
- Uso de protocolos de red como HTTP/HTTPS para la comunicación entre cliente y servidor.
- Planificación del despliegue del proyecto en un entorno real o servidor remoto mediante Docker.
- Integración de servicios externos como el envío de correos de confirmación de reserva mediante SMTP (msmtp + Gmail) y flujo de pago integrado con pasarela simulada.

---

## Gestión de Bases de Datos

Este módulo se relaciona con la creación y la implantación de la base de datos en la página web:

- Creación del diagrama entidad/relación de la base de datos.
- Creación del modelo relacional de la base de datos.
- Elaboración de la base de datos en el lenguaje SQL usando MySQL 8.
- Uso de consultas de la base de datos.

---

## Empresa e Iniciativa Emprendedora

Este módulo se relaciona con el proyecto como caso práctico de creación de una empresa digital en el sector turístico:

- Análisis del mercado turístico digital y la oportunidad de negocio que representa frente a las agencias de viajes tradicionales.
- Definición del modelo de negocio de la plataforma: ingresos por comisiones sobre reservas, venta de seguros de cancelación y servicios complementarios de alquiler de vehículos.
- Identificación del público objetivo y ventajas competitivas de la solución desarrollada frente a la competencia.
- El panel de administración actúa como herramienta de gestión empresarial básica, mostrando KPIs clave: ingresos totales, número de reservas activas, destinos más visitados y usuarios registrados.
- Estudio de la viabilidad técnica y económica del proyecto como producto software escalable, mantenible y con capacidad de crecimiento.
- Aplicación de la iniciativa emprendedora al proceso de digitalización de un negocio convencional, reduciendo costes operativos y ampliando el alcance de mercado.