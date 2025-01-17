## TKZ Gastos

TKZ Gastos es una aplicación en un único archivo PHP que muestra un control de gastos e ingresos, tomando los datos de un fichero Excel (gastos.xlsx) o de un documento OpenDocument (gastos.odf) en caso de que el primero no exista.

![imagen](https://github.com/user-attachments/assets/24767c3a-a210-4709-86b1-5206046d4baf)


# Características
- Script único en PHP: no requiere panel de administración; los datos se editan externamente en Excel u ODF.
- Lectura de datos: Columna A (fecha), B (concepto), C (monto). Monto positivo = ingreso, negativo = gasto.
- Filtros de fechas: Permite seleccionar un rango de fecha personalizado.
- Navegación de meses: Botones para ir al mes anterior o al mes siguiente.
- Cálculo de Saldo Inicial y Final del periodo, junto con total de ingresos y gastos.
- Gráfica con Chart.js que muestra la evolución diaria (para el rango seleccionado).
- Idiomas: El sistema detecta el idioma del navegador y se puede seleccionar manualmente. Varios idiomas disponibles.
- Responsive: Uso de Bootstrap para una visualización adaptable a móvil/tablet/PC.

![imagen](https://github.com/user-attachments/assets/20ce420d-a50e-405d-87c0-53055dbc5929)

# Requisitos
- PHP 7.4+ (o superior).
- Extensiones php_zip, php_xml y php_gd habilitadas (usualmente necesario para PhpSpreadsheet).
- Librería PhpSpreadsheet instalada: *composer require phpoffice/phpspreadsheet*
- Ficheros de idioma: Uno o varios ficheros lang_<LOCALE>.json (por ejemplo, lang_ES.json, lang_EN.json) en el mismo directorio que el script principal PHP.
- Fichero gastos.xlsx (o gastos.odf) con datos, en la primera hoja, comenzando en la fila 2 (porque la fila 1 se considera cabecera). 

Ver imagen:

![imagen](https://github.com/user-attachments/assets/47e7a75d-ce7a-42cc-8e2d-ebbef88039f0)


# Instalación

- Descarga o clona este repositorio.
- Asegúrate de tener instaladas las dependencias de PHP (ver sección de Requisitos).
- Coloca tu archivo gastos.xlsx (o gastos.odf) junto al script principal PHP.
- Crea (o comprueba que existan) los archivos de idioma lang_<LOCALE>.json en el mismo directorio.
- Sube el contenido a tu servidor web con PHP habilitado.

# Uso
- Accede directamente a la dirección del script PHP desde tu navegador.

© 2025 - TKZ Gastos
GitHub: https://github.com/trankten/tkzgastos/
