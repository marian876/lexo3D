# LEXO

## Documento funcional y técnico --- Descripción completa del proyecto

**Nombre del producto:** LEXO (nombre provisional)

**Eslogan propuesto:** El desafío de las palabras.

**Versión:** 1.1\
**Estado:** Especificación inicial para desarrollo\
**Tipo de producto:** Aplicación web multijugador para un juego físico
de formación de palabras\
**Idioma inicial:** Español\
**Zona horaria de referencia:** Configurable por sala/servidor

------------------------------------------------------------------------

## 1. Visión general

**LEXO** es una aplicación web que acompaña a **Lexo 3D**, un juego físico de
letras impreso en 3D compartido en la mesa. Los elementos físicos son los que generan las
letras que utilizan los participantes de la sala. La aplicación **no debe mostrar,
generar ni administrar las letras disponibles**: su función es organizar
la competencia, controlar el tiempo, registrar las palabras que escribe
cada jugador, calcular resultados y conservar el progreso de cada
cuenta.

En cada ronda, todos los participantes de una misma sala utilizan el mismo **Lexo 3D**, forman
tantas palabras como puedan dentro del tiempo establecido y las
registran en sus dispositivos. Al finalizar el tiempo, la aplicación
cierra el ingreso de respuestas, procesa las palabras según las reglas
configuradas y determina el resultado de la ronda.

La experiencia debe sentirse como un videojuego social: visualmente
lúdica, dinámica, colorida y atractiva para adolescentes y adultos, sin
parecer una plataforma escolar ni un panel administrativo.

Cada participante puede registrarse e iniciar sesión con su cuenta de
Google. Su perfil conserva el historial de partidas, puntos, estrellas,
experiencia, nivel, estadísticas e insignias, incluso si vuelve a
ingresar desde otro dispositivo.

## 2. Objetivos del producto

1.  Permitir que varias personas participen en una misma sala en tiempo
    real.
2.  Facilitar la creación de salas privadas mediante un código de
    acceso.
3.  Permitir que cada jugador ingrese sus palabras desde su propio
    dispositivo.
4.  Proporcionar un temporizador sincronizado y controlado por el
    servidor.
5.  Cerrar automáticamente la ronda cuando termina el tiempo.
6.  Registrar y conservar los resultados de cada ronda y partida.
7.  Mantener un perfil persistente para cada jugador mediante
    autenticación con Google.
8.  Incentivar la continuidad del juego mediante puntos, estrellas,
    experiencia, niveles y logros.
9.  Ofrecer un historial personal y clasificaciones.
10. Funcionar correctamente en computadoras, tabletas y teléfonos.

## 3. Principios fundamentales del diseño

### 3.1 Lexo 3D es la fuente compartida de las letras

-   Todos los jugadores de la misma sala utilizan el mismo **Lexo 3D**.
-   Las letras se obtienen exclusivamente mediante el mecanismo físico.
-   La aplicación no presenta una cuadrícula de letras ni una
    combinación común.
-   La aplicación no necesita conocer las letras que obtuvo cada
    participante.
-   El jugador es responsable de comprobar que cada palabra puede
    formarse con las letras que obtuvo.
-   La aplicación registra las palabras y puede validar su existencia en
    el idioma seleccionado, pero no puede comprobar que sean
    construibles con las letras físicas si estas no se ingresan en el
    sistema.

### 3.2 Competencia simultánea

-   Todos los jugadores de una sala participan en la misma ronda.
-   El inicio y el final de la ronda deben ser comunes para todos.
-   Cada participante registra sus respuestas de manera privada durante
    la ronda.
-   Los jugadores no deben ver las palabras que están ingresando sus
    oponentes mientras la ronda está activa.
-   Al terminar, se presentan los resultados de acuerdo con las reglas
    de la partida.

### 3.3 Progreso persistente

-   Cada jugador tiene una identidad única vinculada a su cuenta de
    Google.
-   Las estadísticas y recompensas se guardan en la base de datos.
-   Una partida debe quedar asociada a los participantes que jugaron,
    aunque alguno cierre el navegador.
-   Los resultados confirmados no deben depender de los datos
    almacenados únicamente en el navegador.

------------------------------------------------------------------------

## 4. Usuarios y roles

### 4.1 Visitante

Puede: - Ver la página de presentación. - Consultar las reglas del
juego. - Iniciar sesión con Google.

No puede: - Crear una sala. - Unirse a una partida. - Registrar
palabras. - Acumular recompensas.

### 4.2 Jugador registrado

Puede: - Iniciar sesión con Google. - Completar o editar los datos
permitidos de su perfil. - Crear una sala. - Unirse a una sala mediante
código. - Consultar la sala de espera. - Participar en rondas. -
Registrar y consultar sus propias palabras durante una ronda. -
Consultar resultados. - Ver su historial, estadísticas, puntos,
estrellas, nivel e insignias. - Consultar rankings disponibles. -
Abandonar una sala.

### 4.3 Anfitrión de la sala

Es el jugador que crea la sala. Además de las funciones de jugador,
puede: - Configurar la partida. - Compartir el código de acceso. - Ver
quién se ha unido y quién está listo. - Iniciar la partida cuando se
cumplan las condiciones. - Controlar el avance entre rondas. - Cancelar
una partida antes de comenzar. - Finalizar una sala cuando corresponda.

El anfitrión no puede modificar arbitrariamente los resultados ni
asignarse recompensas manualmente.

### 4.4 Administrador de la plataforma

Rol reservado para la gestión del sistema. Puede: - Consultar usuarios y
partidas. - Gestionar el catálogo de logros. - Configurar las reglas de
puntuación y recompensas. - Gestionar diccionarios y criterios de
validación. - Consultar reportes de uso. - Suspender cuentas cuando
exista una razón justificada. - Revisar incidencias y registros
técnicos.

Las acciones administrativas deben quedar registradas.

------------------------------------------------------------------------

## 5. Autenticación y perfil de usuario

### 5.1 Inicio de sesión con Google

La aplicación utilizará OAuth 2.0 / OpenID Connect mediante Google.

Flujo esperado: 1. El usuario selecciona **Continuar con Google**. 2.
Google realiza el proceso de autenticación. 3. El servidor recibe y
verifica la respuesta de autenticación. 4. Se identifica al usuario
mediante el identificador único de Google (`sub`), no solamente por su
correo. 5. Si es la primera vez, se crea el perfil. 6. Si ya existe, se
inicia su sesión y se recuperan sus datos.

No se almacenan contraseñas de Google ni se solicita la contraseña del
usuario dentro de LEXO.

### 5.2 Datos del perfil

Datos iniciales: - Identificador interno. - Identificador de Google. -
Nombre. - Apellido, si Google los proporciona. - Nombre visible o apodo
de jugador. - Correo electrónico. - URL de fotografía de perfil, si está
disponible. - Fecha de registro. - Último acceso. - Estado de la cuenta.

El nombre visible debe poder distinguir al jugador dentro de una sala.
Se recomienda permitir un apodo editable y controlar que no esté vacío
ni contenga contenido inapropiado.

### 5.3 Persistencia

El perfil y las estadísticas deben recuperarse al iniciar sesión desde
cualquier dispositivo. El cierre de sesión no elimina el historial.

------------------------------------------------------------------------

## 6. Estructura de navegación

La aplicación tendrá las siguientes secciones principales:

1.  **Inicio**
2.  **Jugar**
3.  **Mi perfil**
4.  **Mi historial**
5.  **Mis logros**
6.  **Ranking**
7.  **Reglas**
8.  **Configuración**

En pantallas pequeñas, la navegación puede convertirse en un menú
inferior o menú desplegable. La acción principal, **Jugar**, debe ser
fácil de encontrar.

## 7. Identidad visual y experiencia de usuario

### 7.1 Dirección estética

La interfaz debe tener una estética de videojuego casual competitivo: -
Colores vivos y contrastantes. - Fondo limpio y composiciones con
energía visual. - Tipografía redondeada, clara y legible. - Botones
amplios, redondeados y con estados visibles. - Tarjetas para perfiles,
salas, resultados y logros. - Letras como recurso gráfico decorativo,
sin representar las letras disponibles de una ronda. - Estrellas,
trofeos, medallas, coronas y elementos de recompensa. - Avatares y
fotografías de perfil. - Animaciones breves para transiciones y
recompensas. - Efectos de sonido opcionales y controlables.

Evitar: - Apariencia de blog generado automáticamente. - Exceso de
degradados, brillos o efectos decorativos. - Interfaz infantilizada. -
Paneles administrativos como pantalla principal. - Texto pequeño o
controles difíciles de usar en teléfonos.

### 7.2 Accesibilidad y usabilidad

-   Contraste suficiente entre texto y fondo.
-   Tamaños táctiles adecuados.
-   Estados claros para botones, campos y errores.
-   No depender únicamente del color para comunicar estados.
-   Respetar la preferencia de movimiento reducido del dispositivo.
-   Permitir silenciar sonidos y animaciones.
-   Mostrar claramente el tiempo restante y el estado de la ronda.

### 7.3 Diseño adaptable

La aplicación debe ser responsive: - Escritorio: aprovechar el espacio
para mostrar sala, estado de jugadores y área de escritura. - Tableta:
mantener los controles principales visibles. - Móvil: priorizar
temporizador, campo para escribir y lista de palabras propias.

------------------------------------------------------------------------

## 8. Pantallas y comportamiento

### 8.1 Página de inicio

Debe mostrar: - Identidad de LEXO. - Mensaje breve que explique el
desafío. - Botón **Jugar**. - Botón **Cómo se juega**. - Acceso a inicio
de sesión o perfil. - Si el usuario está autenticado: avatar, apodo,
nivel y estrellas. - Resumen de estadísticas relevantes. - Acceso al
historial y logros.

La página no debe mostrar letras de una partida en curso.

### 8.2 Inicio de sesión

Elementos: - Logo y nombre. - Mensaje de bienvenida. - Botón **Continuar
con Google**. - Enlaces a reglas, privacidad y condiciones de uso.

### 8.3 Menú Jugar

Opciones: - **Crear sala**. - **Unirme a una sala** mediante código. -
Opcionalmente, volver a una sala activa si el jugador tiene una
participación pendiente.

### 8.4 Crear sala

El anfitrión podrá configurar: - Nombre opcional de la sala. - Cantidad
de rondas. - Duración de cada ronda. - Modalidad de puntuación. -
Cantidad máxima de participantes. - Visibilidad: privada mediante código
en la primera versión.

Valores iniciales recomendados: - Jugadores: mínimo 2, máximo 8. -
Duración: 30, 60, 90 o 120 segundos. - Rondas: 1, 3, 5 o 10, con
posibilidad de configuración. - Idioma de palabras: español.

Los valores deben ser configurables desde el sistema, no estar dispersos
como constantes en el código.

### 8.5 Unirse a una sala

El jugador: 1. Ingresa el código. 2. El servidor verifica que la sala
exista. 3. Comprueba que esté abierta y admita participantes. 4.
Comprueba que el jugador no esté ya registrado. 5. Lo incorpora a la
sala. 6. Muestra la sala de espera.

Se deben mostrar mensajes claros para códigos incorrectos, salas
completas, partidas iniciadas o salas cerradas.

### 8.6 Sala de espera

Debe mostrar: - Código de sala, con acción para copiarlo. - Botón o
mecanismo para compartir el código. - Nombre del anfitrión. - Lista de
participantes. - Avatar y apodo de cada participante. - Estado de
conexión. - Estado de preparación, si se implementa. - Configuración de
la partida. - Botón **Comenzar partida**, disponible solamente para el
anfitrión y cuando se cumplan las condiciones.

Si un jugador se desconecta, su estado debe actualizarse. Una
desconexión temporal no debe eliminar inmediatamente su participación.

### 8.7 Pantalla de partida

Elementos principales: - Nombre de la sala. - Número de ronda y total de
rondas. - Temporizador grande. - Barra visual de tiempo restante. -
Campo para escribir una palabra. - Botón para agregar. - Lista de
palabras propias registradas. - Cantidad de palabras ingresadas. -
Estado de conexión. - Mensajes de estado.

**No mostrar:** - Letras disponibles. - Combinaciones de letras. -
Palabras de otros participantes durante la ronda. - Una lista común de
respuestas.

Comportamiento del ingreso: 1. El jugador escribe una palabra. 2. La
interfaz normaliza el texto según las reglas definidas. 3. El servidor
verifica que la ronda siga activa. 4. Se comprueba que el usuario
pertenezca a la ronda. 5. Se comprueba que no haya registrado ya esa
palabra. 6. Se ejecuta la validación léxica, si está habilitada. 7. Se
guarda la respuesta con su fecha y hora. 8. Se actualiza la lista
personal.

El jugador debe recibir una confirmación clara o un mensaje de error. La
interfaz debe permitir ingresar palabras rápidamente, sin obligar a usar
el mouse entre cada respuesta. En móvil, el teclado debe permanecer
disponible mientras sea posible.

### 8.8 Fin de ronda

Cuando el servidor determina que terminó el tiempo: - Se bloquean nuevas
respuestas. - La interfaz muestra que la ronda terminó. - Se conserva la
lista de palabras registradas. - Se procesan las respuestas. - Se
calcula el resultado de cada jugador. - Se presenta la pantalla de
resultados.

No debe aceptarse una respuesta cuya recepción por el servidor haya
ocurrido después del cierre de la ronda. El criterio temporal debe ser
único y consistente para todos.

### 8.9 Resultados de ronda

Mostrar: - Número de ronda. - Participantes. - Cantidad de palabras
válidas de cada uno. - Palabras registradas por cada jugador, según las
reglas de privacidad de la partida. - Palabras rechazadas y motivo,
cuando corresponda. - Ganador de la ronda. - Puntos obtenidos. -
Desempate, si lo hubo.

La pantalla debe destacar visualmente al ganador, sin ocultar los
resultados de los demás.

### 8.10 Resultado final de partida

Mostrar: - Podio o clasificación final. - Ganador general. - Resultados
acumulados. - Rondas ganadas. - Puntos obtenidos en la partida. -
Recompensas y experiencia recibidas. - Nuevos logros desbloqueados. -
Acciones: **Revancha**, **Volver al inicio** y, si corresponde,
**Compartir resultado**.

La partida debe quedar guardada en el historial de todos los
participantes.

### 8.11 Perfil personal

El perfil debe mostrar: - Avatar. - Apodo. - Nombre visible. - Nivel
actual. - Barra de experiencia. - Puntos acumulados. - Estrellas. -
Partidas jugadas. - Victorias. - Porcentaje de victorias. - Total de
palabras válidas. - Racha actual o mejor racha. - Insignias obtenidas. -
Fecha de incorporación.

Los datos se calculan a partir de partidas y eventos registrados. Las
estadísticas agregadas pueden almacenarse para mejorar el rendimiento,
pero deben poder reconstruirse desde los registros originales.

### 8.12 Historial

Cada registro debe mostrar: - Fecha y hora. - Nombre o código de la
sala. - Participantes. - Resultado del jugador. - Posición final. -
Puntos obtenidos. - Cantidad de rondas. - Acceso al detalle.

El detalle de una partida permite consultar sus rondas, resultados y
palabras registradas, respetando las reglas de visibilidad.

### 8.13 Logros e insignias

La sección muestra: - Logros desbloqueados. - Logros pendientes. -
Descripción de cada logro. - Condición para obtenerlo. - Recompensa. -
Fecha de desbloqueo.

Ejemplos iniciales: - **Primera victoria:** ganar una ronda. -
**Campeón:** ganar una partida. - **Velocista:** registrar una cantidad
configurable de palabras válidas en una ronda. - **Constante:**
participar en varias partidas. - **Imparable:** obtener una racha de
victorias. - **Coleccionista:** alcanzar una cantidad determinada de
partidas. - **Maestro de palabras:** acumular una cantidad de palabras
válidas. - **Leyenda:** alcanzar un objetivo avanzado de victorias.

Los umbrales deben ser configurables desde administración.

### 8.14 Ranking

La primera versión puede incluir: - Ranking global por puntos. - Ranking
por victorias. - Ranking por experiencia o nivel.

Debe definirse un período o alcance para evitar que una clasificación
histórica se vuelva poco útil. Se recomienda contemplar filtros como
general, mensual y semanal, aunque pueden incorporarse en una etapa
posterior.

No se deben exponer correos electrónicos ni datos privados en las
clasificaciones.

------------------------------------------------------------------------

## 9. Reglas del juego

### 9.1 Reglas generales

-   Todos los jugadores de la misma sala utilizan el mismo **Lexo 3D**.
-   Cada participante obtiene sus letras mediante el mecanismo físico.
-   Todos compiten durante el mismo intervalo de tiempo.
-   Cada jugador registra sus propias palabras.
-   Una palabra no puede repetirse en la lista del mismo jugador dentro
    de una ronda.
-   Las respuestas quedan cerradas al terminar el tiempo.
-   La aplicación no comprueba que la palabra pueda formarse con las
    letras físicas, salvo que en una versión futura se agregue un
    mecanismo para registrar esas letras.

### 9.2 Reglas léxicas iniciales recomendadas

-   Idioma inicial: español.
-   No aceptar nombres propios, salvo que una modalidad futura lo
    permita.
-   No aceptar abreviaturas no contempladas por el diccionario.
-   No aceptar cadenas vacías ni palabras formadas únicamente por
    espacios.
-   Definir una longitud mínima configurable; propuesta inicial: 2
    letras.
-   Ignorar mayúsculas y minúsculas para evitar duplicados como `casa` y
    `CASA`.
-   Definir una política para tildes. Recomendación inicial: conservar
    la escritura original, pero comparar de forma normalizada para
    evitar duplicados por diferencias de acentuación cuando la regla del
    juego así lo determine.
-   Definir explícitamente el tratamiento de plurales, conjugaciones,
    regionalismos y variantes.

### 9.3 Validación de palabras

La validación automática puede utilizar un diccionario español o una
fuente léxica local. Debe evaluarse: - Cobertura del vocabulario. -
Licencia y condiciones de uso. - Capacidad de funcionar sin conexión a
servicios externos. - Tratamiento de regionalismos. - Actualización del
vocabulario.

La validación automática no es infalible. Por eso se recomienda una
modalidad combinada futura: - El sistema acepta o marca una palabra
según el diccionario. - Los participantes pueden señalar una palabra
dudosa después de la ronda. - Se aplica un procedimiento de revisión
definido. - Los cambios de resultado quedan auditados.

Para el MVP, se puede comenzar con validación automática y dejar la
revisión manual para una etapa posterior. Si el diccionario no está
disponible, el sistema debe informar el problema y no fingir que validó
correctamente.

### 9.4 Privacidad de las respuestas

Durante la ronda, cada jugador ve solamente sus propias palabras. Al
finalizar, la sala puede mostrar las respuestas para comparar
resultados. Esta regla debe ser uniforme y explicarse a los
participantes.

------------------------------------------------------------------------

## 10. Sistema de puntuación, estrellas y experiencia

Son tres conceptos diferentes y no deben mezclarse.

### 10.1 Puntos competitivos

Representan el rendimiento en las partidas. Se utilizan para rankings y
estadísticas.

Modalidad recomendada para comenzar: - Cada palabra válida cuenta como
una palabra. - Gana la ronda quien registre más palabras válidas. - El
ganador recibe un punto de victoria de ronda o la cantidad de puntos
competitivos definida. - La partida se resuelve por cantidad de rondas
ganadas; en caso de empate, se aplica un criterio de desempate
configurado.

Como alternativa, se puede activar una modalidad por longitud: - 2
letras: 1 punto. - 3 letras: 2 puntos. - 4 letras: 3 puntos. - 5 letras:
4 puntos. - 6 o más letras: 5 puntos.

La modalidad activa debe quedar guardada en la configuración de cada
partida.

### 10.2 Estrellas

Las estrellas representan recompensas por logros. No deben confundirse
con los puntos de clasificación.

Ejemplos: - Primera victoria: 1 estrella. - Cinco victorias: recompensa
configurable. - Logro especial: recompensa configurable.

El sistema debe impedir que una misma recompensa de logro se entregue
repetidamente, salvo que el logro esté definido expresamente como
repetible.

### 10.3 Experiencia (XP) y niveles

La experiencia refleja la participación y el progreso general.

Valores iniciales ilustrativos: - Participar en una partida finalizada:
5 XP. - Ganar una partida: 20 XP adicionales. - Desbloquear determinados
logros: XP adicional.

Los niveles se calculan según una tabla o fórmula configurable. La
interfaz muestra el nivel actual y el progreso hacia el siguiente.

### 10.4 Reglas de integridad de recompensas

-   Las recompensas se calculan en el servidor.
-   Se registran como movimientos o eventos.
-   No se confía en valores enviados por el navegador.
-   No se otorgan recompensas por una partida cancelada.
-   Debe definirse qué ocurre si un jugador abandona una partida.
-   Los eventos de recompensa deben ser idempotentes: procesar dos veces
    el mismo resultado no debe duplicar puntos, XP o estrellas.

------------------------------------------------------------------------

## 11. Flujo completo de una partida

1.  El jugador inicia sesión.
2.  Crea una sala o ingresa el código de una existente.
3.  Los participantes se incorporan.
4.  El anfitrión revisa la lista y la configuración.
5.  El anfitrión inicia la partida.
6.  El servidor crea la partida y la primera ronda.
7.  El servidor registra el instante de inicio y el instante de
    finalización.
8.  Todos los clientes reciben el estado de ronda activa.
9.  Los jugadores de la sala utilizan su **Lexo 3D** compartido.
10. Cada jugador registra sus palabras.
11. El servidor valida y guarda cada respuesta.
12. El servidor cierra la ronda al llegar el momento establecido.
13. Se procesan las respuestas y se calcula el resultado.
14. Se muestran los resultados.
15. Se inicia la siguiente ronda, si corresponde.
16. Al terminar todas las rondas, se calcula la clasificación final.
17. Se registran puntos, XP, estrellas y logros.
18. Se guarda el historial para todos los participantes.
19. Los jugadores pueden iniciar una revancha o salir.

------------------------------------------------------------------------

## 12. Comunicación y sincronización en tiempo real

La sincronización es un requisito central.

### 12.1 Autoridad del servidor

El servidor debe ser la fuente de verdad para: - Estado de la sala. -
Participantes. - Inicio y final de cada ronda. - Tiempo restante. -
Aceptación o rechazo de respuestas. - Resultados. - Recompensas.

El navegador solamente presenta el estado recibido y envía acciones.

### 12.2 Temporizador

No se debe confiar en un contador JavaScript independiente de cada
dispositivo.

Al iniciar la ronda, el servidor guarda: - `started_at` - `ends_at` -
`status`

Cada cliente calcula visualmente el tiempo restante a partir de la hora
de finalización recibida, pero el servidor decide si una respuesta llegó
a tiempo.

### 12.3 Tecnología de comunicación

Alternativas: - **Primera versión:** peticiones AJAX periódicas para
consultar cambios y enviar acciones. - **Versión más fluida:**
WebSockets para difundir cambios de sala, inicio, final y resultados.

La elección depende de las posibilidades del alojamiento de Hostinger.
Antes de decidir WebSockets, se debe verificar que el plan permita
ejecutar y mantener el proceso necesario. Si no está disponible, se
implementará una estrategia de consulta periódica con intervalos
razonables y control de carga.

La aplicación debe evitar crear una consulta por cada segundo de forma
innecesaria. Puede enviar el instante de finalización y dejar que el
cliente actualice visualmente el contador.

### 12.4 Desconexiones

-   Una desconexión no debe borrar respuestas ya guardadas.
-   El jugador puede volver a ingresar a la sala si la partida sigue
    activa.
-   Al reconectarse, debe recuperar el estado actual.
-   El servidor debe impedir que una misma cuenta genere participaciones
    duplicadas en una misma ronda.
-   Debe definirse si el jugador desconectado puede continuar
    registrando palabras al volver.

------------------------------------------------------------------------

## 13. Arquitectura técnica propuesta

### 13.1 Tecnologías

  Capa                    Tecnología propuesta
  ----------------------- --------------------------------------
  Frontend                HTML5, CSS3 y JavaScript
  Backend                 PHP
  Base de datos           MySQL
  Autenticación           Google OAuth 2.0 / OpenID Connect
  Comunicación inicial    AJAX
  Comunicación avanzada   WebSockets, si el hosting lo permite
  Diseño                  Responsive, con posibilidad de PWA
  Alojamiento             Hostinger

La implementación debe utilizar versiones compatibles con el entorno
real de producción y mantener las credenciales fuera del repositorio
público.

### 13.2 Organización sugerida del proyecto

Una estructura posible:

``` text
word-arena/
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   ├── Middleware/
│   ├── Validators/
│   └── Support/
├── config/
│   ├── app.php
│   ├── database.php
│   └── services.php
├── database/
│   ├── migrations/
│   └── seeds/
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   ├── images/
│   │   └── sounds/
│   └── manifest.webmanifest
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
│   ├── logs/
│   └── cache/
├── tests/
├── .env.example
└── README.md
```

La estructura es orientativa. Se puede adaptar al framework elegido,
siempre que se mantengan separadas las responsabilidades.

### 13.3 Seguridad

-   Verificar en el servidor las credenciales de Google.
-   Usar sesiones seguras y cookies `HttpOnly`, `Secure` y `SameSite`
    cuando corresponda.
-   Proteger formularios y acciones contra CSRF.
-   Utilizar consultas preparadas para evitar inyección SQL.
-   Validar y limitar todos los datos recibidos.
-   Aplicar límites de frecuencia para evitar abuso.
-   Verificar que cada acción pertenezca al usuario autenticado.
-   Verificar que el jugador pertenezca a la sala y ronda
    correspondiente.
-   No exponer claves privadas ni secretos de OAuth en JavaScript.
-   Registrar errores sin almacenar credenciales ni tokens sensibles.
-   Mantener HTTPS obligatorio en producción.
-   Aplicar controles de autorización para administración.

------------------------------------------------------------------------

## 14. Modelo de datos propuesto

El esquema final se ajustará durante el diseño de base de datos. Las
entidades principales son:

### 14.1 `users`

Datos de identidad y perfil: - `id` - `google_sub` - `email` -
`first_name` - `last_name` - `display_name` - `avatar_url` - `role` -
`status` - `created_at` - `updated_at` - `last_login_at`

`google_sub` debe ser único.

### 14.2 `rooms`

Salas: - `id` - `code` - `name` - `host_user_id` - `status` -
`max_players` - `round_count` - `round_duration_seconds` -
`scoring_mode` - `language` - `created_at` - `started_at` -
`finished_at`

El código debe ser único mientras la sala esté activa. Debe definirse
una política de expiración para salas abandonadas.

### 14.3 `room_players`

Relación entre jugadores y salas: - `id` - `room_id` - `user_id` -
`status` - `is_host` - `joined_at` - `left_at` - `connection_state`

Debe existir una restricción que impida duplicar al mismo usuario dentro
de la misma participación activa.

### 14.4 `matches`

Partidas: - `id` - `room_id` - `status` - `scoring_mode_snapshot` -
`round_count_snapshot` - `round_duration_snapshot` - `started_at` -
`finished_at` - `created_at`

Se guardan copias de las reglas relevantes para que un cambio futuro de
configuración no altere partidas anteriores.

### 14.5 `match_players`

Participantes de una partida: - `id` - `match_id` - `user_id` -
`final_position` - `rounds_won` - `valid_word_count` -
`competitive_points` - `experience_earned` - `stars_earned` - `status`

### 14.6 `rounds`

Rondas: - `id` - `match_id` - `round_number` - `status` - `started_at` -
`ends_at` - `finished_at` - `winner_user_id` - `created_at`

### 14.7 `words`

Palabras registradas: - `id` - `round_id` - `user_id` -
`original_text` - `normalized_text` - `validation_status` -
`validation_source` - `rejection_reason` - `submitted_at`

Debe existir una restricción para impedir palabras duplicadas por
jugador y ronda según la política de normalización definida.

### 14.8 `round_results`

Resultado por jugador y ronda: - `id` - `round_id` - `user_id` -
`valid_word_count` - `competitive_points` - `position` - `is_winner` -
`created_at`

### 14.9 `player_stats`

Estadísticas acumuladas: - `user_id` - `matches_played` -
`matches_won` - `rounds_played` - `rounds_won` - `valid_words_total` -
`competitive_points_total` - `experience_total` - `stars_total` -
`current_win_streak` - `best_win_streak` - `updated_at`

Estas estadísticas deben derivarse de registros verificables y poder
recalcularse.

### 14.10 `achievements`

Catálogo: - `id` - `code` - `name` - `description` - `icon` -
`condition_type` - `condition_value` - `reward_points` -
`reward_experience` - `reward_stars` - `is_repeatable` - `is_active`

### 14.11 `player_achievements`

Logros de cada usuario: - `id` - `user_id` - `achievement_id` -
`unlocked_at` - `reward_granted`

Debe impedirse la duplicación de logros no repetibles.

### 14.12 `reward_transactions`

Registro de recompensas: - `id` - `user_id` - `source_type` -
`source_id` - `reward_type` - `amount` - `created_at` -
`idempotency_key`

Permite auditar cómo se obtuvo cada punto, estrella o experiencia.

### 14.13 `app_settings`

Configuraciones generales: - `setting_key` - `setting_value` -
`updated_at`

Puede contener valores de juego, límites, reglas y parámetros
configurables.

------------------------------------------------------------------------

## 15. Reglas de integridad y casos especiales

El sistema debe contemplar, como mínimo:

-   El anfitrión cierra la sala antes de iniciar.
-   Un participante abandona la sala de espera.
-   Un participante pierde conexión durante una ronda.
-   El anfitrión pierde conexión.
-   El anfitrión intenta iniciar con menos de dos jugadores.
-   Se intenta ingresar a una sala llena.
-   Se intenta ingresar a una sala cuya partida ya comenzó.
-   Se envía una palabra duplicada.
-   Se envía una palabra vacía o inválida.
-   Se envía una palabra cuando el tiempo terminó.
-   Dos jugadores terminan empatados.
-   Todos terminan empatados.
-   Se cancela una partida.
-   El servidor se reinicia durante una partida.
-   Un jugador intenta enviar respuestas a una ronda ajena.
-   Se reintenta procesar un resultado ya calculado.

Los resultados y recompensas deben procesarse de manera transaccional
para evitar registros parciales o duplicados.

------------------------------------------------------------------------

## 16. Administración

Se recomienda una sección administrativa protegida por rol.

Funciones iniciales: - Consultar usuarios. - Consultar partidas y
salas. - Consultar resultados. - Gestionar logros. - Configurar puntos y
recompensas. - Configurar duración y cantidad de rondas disponibles. -
Gestionar el diccionario o fuente de validación. - Consultar errores e
incidencias. - Recalcular estadísticas cuando sea necesario.

No es necesario desarrollar un panel administrativo complejo en la
primera entrega, pero sí dejar preparada la estructura de permisos y
configuración.

------------------------------------------------------------------------

## 17. Privacidad y protección de datos

-   Solicitar a Google solamente los datos necesarios para identificar y
    mostrar al jugador.
-   Informar qué datos se guardan y con qué finalidad.
-   No publicar correos electrónicos en perfiles o rankings.
-   Permitir cerrar sesión.
-   Definir un procedimiento para solicitar la eliminación de una cuenta
    y sus datos, respetando las obligaciones aplicables.
-   Proteger fotografías y datos de perfil de acuerdo con la
    configuración de privacidad.
-   Mantener secretos y tokens fuera de registros públicos.
-   Publicar política de privacidad y condiciones de uso antes del
    lanzamiento público.

------------------------------------------------------------------------

## 18. Requisitos no funcionales

### Rendimiento

-   La pantalla de partida debe responder rápidamente al registrar
    palabras.
-   La sincronización no debe generar consultas innecesarias.
-   Los rankings e historiales deben utilizar consultas eficientes.
-   Debe contemplarse el crecimiento de usuarios y partidas.

### Disponibilidad

-   Los errores de conexión deben comunicarse claramente.
-   Las respuestas ya guardadas no deben perderse por una desconexión.
-   Las partidas deben poder recuperarse después de una recarga del
    navegador cuando sigan activas.

### Compatibilidad

-   Navegadores modernos de escritorio y móviles.
-   Diseño adaptable.
-   Uso cómodo con teclado físico y teclado táctil.

### Mantenibilidad

-   Código organizado por responsabilidades.
-   Configuración centralizada.
-   Migraciones de base de datos.
-   Registros de errores.
-   Pruebas de las reglas críticas.
-   Documentación de instalación y despliegue.

------------------------------------------------------------------------

## 19. Alcance de desarrollo por etapas

### Etapa 1 --- MVP funcional

Objetivo: permitir partidas reales entre usuarios registrados.

Incluye: 1. Identidad visual inicial. 2. Registro e inicio de sesión con
Google. 3. Perfil básico. 4. Crear sala. 5. Unirse mediante código. 6.
Sala de espera. 7. Configuración de rondas y tiempo. 8. Inicio y cierre
sincronizados. 9. Registro individual de palabras. 10. Validación léxica
inicial. 11. Resultados de ronda. 12. Resultado final de partida. 13.
Puntos y estadísticas básicas. 14. Historial personal. 15. Ranking
inicial. 16. Seguridad y controles de acceso.

### Etapa 2 --- Gamificación

Incluye: - Estrellas. - Experiencia y niveles. - Insignias. - Logros
automáticos. - Animaciones de recompensas. - Rachas. - Ranking por
períodos. - Perfil más completo.

### Etapa 3 --- Experiencia social

Incluye: - Lista de amigos. - Invitaciones. - Salas recurrentes. -
Historial compartido. - Revancha rápida. - Desafíos entre grupos. -
Compartir resultados.

### Etapa 4 --- Funciones avanzadas

Posibles ampliaciones: - Diccionario ampliado y revisión de palabras. -
Categorías temáticas. - Estadísticas avanzadas. - Torneos. - Temporadas
y rankings especiales. - Notificaciones. - PWA instalable. - Nuevos
idiomas y variantes de reglas.

------------------------------------------------------------------------

## 20. Criterios de aceptación del MVP

La primera versión se considerará funcional cuando se pueda comprobar
que:

1.  Un usuario puede registrarse e ingresar con Google.
2.  El perfil se conserva al cerrar sesión y volver a ingresar.
3.  Un jugador puede crear una sala y compartir el código.
4.  Otro jugador puede ingresar a esa sala.
5.  La sala muestra correctamente a sus participantes.
6.  El anfitrión puede iniciar una partida con al menos dos jugadores.
7.  Todos reciben el inicio de la ronda de manera sincronizada.
8.  El temporizador se basa en el tiempo del servidor.
9.  Cada participante puede registrar sus propias palabras.
10. Un jugador no puede ver las respuestas de los demás durante la
    ronda.
11. Las palabras duplicadas del mismo jugador se rechazan.
12. Las respuestas recibidas después del cierre se rechazan.
13. La ronda finaliza automáticamente.
14. Se calculan los resultados según la modalidad configurada.
15. Se determina un ganador o empate de forma consistente.
16. La partida y sus resultados quedan guardados.
17. Los participantes pueden consultar el historial.
18. Las estadísticas se actualizan sin duplicarse.
19. La interfaz funciona en teléfonos y computadoras.
20. Un usuario no puede modificar los resultados enviando datos
    manipulados desde el navegador.

------------------------------------------------------------------------

## 21. Decisiones pendientes antes de cerrar la implementación

Estas cuestiones deben confirmarse durante la definición funcional. Se
proponen valores iniciales para no bloquear el desarrollo.

  -----------------------------------------------------------------------
  Tema                    Propuesta inicial       Estado
  ----------------------- ----------------------- -----------------------
  Juego físico            Todos en la sala usan el Definido
                          mismo Lexo 3D           

  Letras en pantalla      Nunca se muestran       Definido

  Registro                Cuenta de Google        Definido

  Cantidad de jugadores   2 a 8                   Propuesto

  Duración                30, 60, 90 o 120        Propuesto
                          segundos                

  Cantidad de rondas      Configurable;           Propuesto
                          sugerencia inicial: 5   

  Ganador de ronda        Mayor cantidad de       Propuesto
                          palabras válidas        

  Ganador de partida      Mayor cantidad de       Confirmar
                          rondas ganadas;         
                          desempate por puntos    

  Competencia             Ranking global y,       Confirmar
                          eventualmente, entre    
                          amigos                  

  Validación              Diccionario español,    Propuesto
                          con revisión futura     

  Longitud mínima         2 letras                Propuesto

  Nombres propios         No permitidos           Propuesto
                          inicialmente            

  Puntos, estrellas y XP  Sistemas separados      Definido como concepto

  Comunicación            AJAX inicial;           Pendiente técnico
                          WebSockets si el        
                          hosting lo permite      

  Nombre                  LEXO, provisional Provisional
  -----------------------------------------------------------------------

------------------------------------------------------------------------

## 22. Recomendaciones de implementación

1.  Confirmar primero las decisiones pendientes que afectan las reglas
    de la partida.
2.  Verificar el plan de Hostinger y sus capacidades antes de elegir la
    estrategia definitiva de comunicación en tiempo real.
3.  Implementar autenticación y perfiles antes de las salas.
4.  Construir el flujo completo de una partida antes de agregar logros
    complejos.
5.  Centralizar las reglas de puntuación y recompensas.
6.  Mantener la lógica crítica en el servidor.
7.  Probar desconexiones, recargas y envíos simultáneos.
8.  Diseñar primero para móvil, porque los participantes probablemente
    ingresarán sus palabras desde sus teléfonos.
9.  Mantener separadas las palabras ingresadas, su validación léxica y
    la puntuación.
10. No agregar funcionalidades sociales o competitivas avanzadas hasta
    que el flujo básico sea estable.

## 23. Definición final del producto

LEXO será una plataforma web multijugador que acompaña un juego
físico de formación de palabras. El juego impreso proporciona las letras
y cada participante utiliza su propio dispositivo para registrar las
palabras que logra formar.

La aplicación organiza las salas, sincroniza el tiempo, registra las
respuestas, calcula los resultados y conserva el progreso individual de
cada jugador. Mediante perfiles vinculados a Google, historial, puntos,
estrellas, experiencia, niveles, insignias y rankings, cada partida
contribuye a una experiencia competitiva que puede continuar a lo largo
del tiempo.

**Principio rector del proyecto: el juego físico propone el desafío;
LEXO organiza la competencia y convierte cada partida en
progreso.**
