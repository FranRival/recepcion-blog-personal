### Plugin puente. Recibe los datos de la cantidad de automatizacion de la pc

Registra:

- Cantidad de horas funcionales de la computadora trabajando de manera automatica. 
- Recibe los datos de las horas (formato 24hrs).
- Registra los datos en el homepage de EmmanuelIbarra.com en formato GitHub. 

#### Cambios 31-3-26

- Tenemos 6 dias perdidos. Del 25 al 30 de marzo. Datos no registrados por fallas en el monitor_json. O en AWS. 
- Creamos una infraestructura para mostrar cuadros rojos dentro del grid. 
- De momento se inyectaran dentro del plugin. 

#### Reflejar la salud al sistema.

Tenemos:  

- Capturador de horas de trabajo automatizadas. 

Pero,... y ya ....   

Ahora necesitamos detectar:  

- 🔴 API caida. 
- 🔴 Uploader inactivo
- 🔴 fallas de sincronizacion
- ⚪ ausencia de datos (pero sistema vivo)
- 🟢 operación normal

Todos estos datos los recibira Status_By_day

#### y en el grid, colocar el tipo de error con un enlace saliente a una pagina explicando el tipo de error.  

La nueva capa sera el Health Monitor  

Señales:  
* ¿La API responde?
* ¿El uploader está activo?
* ¿Se están recibiendo datos hoy?
* ¿Hubo timeout / error HTTP?
* ¿Cuándo fue el último dato recibido?