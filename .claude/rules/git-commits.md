Estándares para realizar commits en Git
Para mantener un historial de cambios organizado, comprensible y fácil de mantener, todos los integrantes del equipo deberán seguir las siguientes convenciones al realizar commits en el repositorio.
1. Estructura del mensaje
Cada commit deberá utilizar la siguiente estructura:
<type>[optional scope]: <description>
O 
<type>[optional scope]: <description>
[optional body]
2. Tipos de commit
Se utilizarán los siguientes tipos:
•	feat: cuando se agrega una nueva funcionalidad.
	o	feat: agregar inicio de sesión
•	fix: cuando se corrige un error o problema.
	o	fix: corregir validación del correo electrónico
•	refactor: cuando se modifica o reorganiza el código sin cambiar su comportamiento.
	o	refactor: reorganizar servicio de usuarios
•	docs: cuando se realizan cambios únicamente en la documentación.
	o	docs: actualizar documentación de instalación
•	style: cuando se realizan cambios de formato, estilos o estructura que no modifican la lógica.
	o	style: ajustar formato del código
•	test: cuando se agregan o modifican pruebas.
	o	test: agregar pruebas para autenticación
•	perf: Cambios que mejoran el rendimiento

•	build: Cambios en el sistema de build o dependencias externas

•	ci: Cambios en archivos y scripts de integración continua
•	chore: para tareas de mantenimiento o configuración del proyecto.
	o	chore: actualizar dependencias
3. Reglas para escribir los commits
Los mensajes de commit deberán cumplir las siguientes reglas:
	1.	Escribir en modo imperativo: "agrega", "corrige", "elimina" (no "agregado" ni "agregando"). 
	2.	No terminar con punto final. 
	3.	Máximo 50 caracteres recomendado (72 como límite duro). 
	4.	Debe ser clara y explicar qué hace el commit, no cómo lo hace. 
	5.	Todo en minúsculas, salvo nombres propios o siglas.
	6.	No hacer commit de archivos generados, secretos o credenciales.
Alcance (scope)
Opcional, entre paréntesis, indica el módulo o parte del sistema afectada:
feat(auth): agrega validación de token expirado
fix(api): corrige timeout en endpoint de usuarios

Cuerpo del commit (opcional)
	1.	Se separa del título con una línea en blanco.
	2.	Explica el por qué del cambio, no solo el qué.
	3.	Se puede usar viñetas para listar varios cambios relacionados.
	4.	Límite recomendado de 72 caracteres por línea.
4. Ejemplos correctos
feat: agrega registro de usuarios
fix: corrige error al iniciar sesión
refactor: separa lógica de autenticación
5. Commits pequeños y específicos
Se recomienda realizar commits pequeños que representen una unidad lógica de trabajo.
Por ejemplo, en lugar de realizar: feat: hacer todo el módulo de usuarios
se pueden realizar varios commits:
feat: crear modelo de usuario
feat: agregar servicio de usuarios
feat: agregar formulario de registro
test: agregar pruebas para registro de usuarios
Esto permite identificar con mayor facilidad qué cambio se realizó, revisar el historial y solucionar problemas.
6. Objetivo
El objetivo de este estándar es mantener un historial de Git ordenado, facilitar la revisión del código, identificar rápidamente los cambios realizados y mejorar la colaboración entre los integrantes del equipo.
