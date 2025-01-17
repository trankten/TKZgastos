<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Default language - Idioma por defecto
define('TKZ_IDIOMA','ES');

// Definir la versión de la app que se muestra a pied e página [NO TOCAR, GRACIAS!] - CAMBIAR SI HAY FORK?
define('TKZ_GASTOS_VERSION', '1.0');


function detectarLocaleNavegador($idiomaDefecto=TKZ_IDIOMA) {
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if (!empty($langs[0])) {
            $parts = explode('-', $langs[0]);
            return strtoupper($parts[count($parts)-1]);
        }
    }
    return $idiomaDefecto;
}

function listarLocalesDisponibles() {
    $files = scandir(__DIR__);
    $locales = [];
    foreach ($files as $f) {
        if (preg_match('/^lang_(.+)\.json$/i', $f, $matches)) {
            $locales[] = strtoupper($matches[1]);
        }
    }
    return $locales;
}

function cargarIdioma($locale) {
    $file = __DIR__."/lang_{$locale}.json";
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $arr = json_decode($json, true);
    if (!is_array($arr)) {
        return [];
    }
    return $arr;
}

function cargarDatos() {
    $excelPath = 'gastos.xlsx';
    $odfPath = 'gastos.odf';
    $filePath = null;
    if (file_exists($excelPath)) {
        $filePath = $excelPath;
    } elseif (file_exists($odfPath)) {
        $filePath = $odfPath;
    } else {
        return [];
    }
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $data = [];
    for ($row = 2; $row <= $highestRow; $row++) {
        $fechaCell = $sheet->getCell("A{$row}")->getValue();
        $conceptoCell = $sheet->getCell("B{$row}")->getValue();
        $montoCell = $sheet->getCell("C{$row}")->getValue();
        if (empty($fechaCell) && empty($conceptoCell) && empty($montoCell)) {
            continue;
        }
        $fechaISO = null;
        if (is_numeric($fechaCell)) {
            $fechaPhp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaCell);
            $fechaISO = $fechaPhp->format('Y-m-d');
        } else {
            $f1 = DateTime::createFromFormat('Y-m-d', $fechaCell);
            if ($f1) {
                $fechaISO = $f1->format('Y-m-d');
            } else {
                $f2 = DateTime::createFromFormat('d-m-Y', $fechaCell);
                if ($f2) {
                    $fechaISO = $f2->format('Y-m-d');
                } else {
                    continue;
                }
            }
        }
        $monto = (float)$montoCell;
        $tipo = $monto < 0 ? 'gasto' : 'ingreso';
        $monto = abs($monto);
        $fecha_display = (new DateTime($fechaISO))->format('d-m-Y');
        $data[] = [
            'fechaISO' => $fechaISO,
            'fecha_display' => $fecha_display,
            'concepto' => trim($conceptoCell),
            'tipo' => $tipo,
            'monto' => $monto
        ];
    }
    usort($data, function($a, $b){
        return strtotime($a['fechaISO']) - strtotime($b['fechaISO']);
    });
    return $data;
}

function obtenerSaldoAnteriorAFecha($datos, $fechaInicio) {
    $saldo = 0.0;
    $limite = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    if (!$limite) {
        return 0.0;
    }
    foreach ($datos as $mov) {
        $movDate = DateTime::createFromFormat('Y-m-d', $mov['fechaISO']);
        if ($movDate && $movDate < $limite) {
            if ($mov['tipo'] === 'ingreso') {
                $saldo += $mov['monto'];
            } else {
                $saldo -= $mov['monto'];
            }
        }
    }
    return $saldo;
}

function filtrarPorRangoFechas($datos, $fechaInicio, $fechaFin) {
    $resultado = [];
    $start = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    $end = DateTime::createFromFormat('Y-m-d', $fechaFin);
    if (!$start || !$end) {
        return $resultado;
    }
    foreach ($datos as $mov) {
        $movDate = DateTime::createFromFormat('Y-m-d', $mov['fechaISO']);
        if ($movDate && $movDate >= $start && $movDate <= $end) {
            $resultado[] = $mov;
        }
    }
    return $resultado;
}

$localesDisponibles = listarLocalesDisponibles();
$localeBrowser = detectarLocaleNavegador();
$localeSel = isset($_GET['locale']) ? strtoupper($_GET['locale']) : $localeBrowser;
if (!in_array($localeSel, $localesDisponibles)) {
    $localeSel = 'ES';
}

$lang = cargarIdioma($localeSel);
$datos = cargarDatos();

if (empty($datos)) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <title><?php echo isset($lang['title'])?$lang['title']:'Control de Gastos e Ingresos'; ?></title>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body{padding-top:20px;padding-bottom:20px;} .container{max-width:1100px;} .chart-container{position:relative;height:400px;width:100%;margin-top:20px;margin-bottom:20px;}
      </style>
    </head>
    <body>
    <div class="container">
      <h1 class="text-center">
        <?php echo isset($lang['title_html']) ? $lang['title_html'] : (isset($lang['title']) ? $lang['title'] : 'Control de Gastos e Ingresos'); ?>
      </h1>
      <div class="alert alert-warning" role="alert">
        <?php
        echo isset($lang['alert_no_data'])
          ? str_replace(['{xlsx}','{odf}'],['gastos.xlsx','gastos.odf'],$lang['alert_no_data'])
          : 'No se han encontrado datos en gastos.xlsx ni en gastos.odf.';
        ?>
      </div>
    </div>
    <footer class="text-center mt-4">
      <a href="https://github.com/trankten/tkzgastos" target="_blank">
        <?php echo isset($lang['repo_text'])?$lang['repo_text']:'Ver en GitHub'; ?>
      </a>
      -
      <?php
      $versionText = isset($lang['version_text']) ? str_replace('<VERSION>', TKZ_GASTOS_VERSION, $lang['version_text']) : ('TKZ Gastos '.TKZ_GASTOS_VERSION);
      echo $versionText;
      ?>
    </footer>
    </body>
    </html>
    <?php
    exit;
}

$primeraFecha = $datos[0]['fechaISO'];
$ultimaFecha = $datos[count($datos)-1]['fechaISO'];
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : $ultimaFecha;

$startObj = DateTime::createFromFormat('Y-m-d', $fechaInicio);
$endObj = DateTime::createFromFormat('Y-m-d', $fechaFin);

if (!$startObj) {
    $startObj = new DateTime($primeraFecha);
}
if (!$endObj) {
    $endObj = new DateTime($ultimaFecha);
}
if ($startObj > $endObj) {
    $temp = $startObj;
    $startObj = $endObj;
    $endObj = $temp;
}

$fechaInicio = $startObj->format('Y-m-d');
$fechaFin = $endObj->format('Y-m-d');

if (isset($_GET['nav']) && $_GET['nav'] === 'prev') {
    $startObj->modify('-1 month');
    $endObj->modify('-1 month');
    $fechaInicio = $startObj->format('Y-m-d');
    $fechaFin = $endObj->format('Y-m-d');
}
if (isset($_GET['nav']) && $_GET['nav'] === 'next') {
    $startObj->modify('+1 month');
    $endObj->modify('+1 month');
    $fechaInicio = $startObj->format('Y-m-d');
    $fechaFin = $endObj->format('Y-m-d');
}

$movimientosPeriodo = filtrarPorRangoFechas($datos, $fechaInicio, $fechaFin);
$saldoInicial = obtenerSaldoAnteriorAFecha($datos, $fechaInicio);
$totalIngresosPeriodo = 0.0;
$totalGastosPeriodo = 0.0;

foreach ($movimientosPeriodo as $m) {
    if ($m['tipo'] === 'ingreso') {
        $totalIngresosPeriodo += $m['monto'];
    } else {
        $totalGastosPeriodo += $m['monto'];
    }
}

$saldoFinal = $saldoInicial + ($totalIngresosPeriodo - $totalGastosPeriodo);

$historico = [];
foreach ($movimientosPeriodo as $row) {
    $dia = date('Y-m-d', strtotime($row['fechaISO']));
    if (!isset($historico[$dia])) {
        $historico[$dia] = [
            'ingresos' => 0.0,
            'gastos' => 0.0
        ];
    }
    if ($row['tipo'] === 'ingreso') {
        $historico[$dia]['ingresos'] += $row['monto'];
    } else {
        $historico[$dia]['gastos'] += $row['monto'];
    }
}

$fechas = array_keys($historico);
sort($fechas);
$labels = [];
$ingresosArray = [];
$gastosArray = [];
$saldoArray = [];
$saldoAcumulado = $saldoInicial;

foreach ($fechas as $dia) {
    $labels[] = $dia;
    $ing = $historico[$dia]['ingresos'];
    $gas = $historico[$dia]['gastos'];
    $saldoAcumulado += ($ing - $gas);
    $ingresosArray[] = $ing;
    $gastosArray[] = $gas;
    $saldoArray[] = $saldoAcumulado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>
    <?php echo isset($lang['title_html'])
      ? $lang['title_html']
      : (isset($lang['title']) ? $lang['title'] : 'Control de Gastos e Ingresos');
    ?>
  </title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <script src="https://stackpath.bootstrapcdN.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{padding-top:20px;padding-bottom:20px;}
    .container{max-width:1100px;}
    .chart-container{position:relative;height:400px;width:100%;margin-top:20px;margin-bottom:20px;}
  </style>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1>
        <?php echo isset($lang['title_html'])
          ? $lang['title_html']
          : (isset($lang['title']) ? $lang['title'] : 'Control de Gastos e Ingresos');
        ?>
      </h1>
    </div>
    <div>
      <select id="langSelector" class="form-control">
        <?php foreach($localesDisponibles as $l) { ?>
          <option value="<?php echo $l; ?>" <?php if($l === $localeSel) echo 'selected'; ?>>
            <?php echo $l; ?>
          </option>
        <?php } ?>
      </select>
    </div>
  </div>
  <form method="get" class="form-inline mb-3">
    <input type="hidden" name="locale" value="<?php echo htmlspecialchars($localeSel); ?>">
    <div class="form-group mr-2">
      <label for="fecha_inicio" class="mr-2">
        <?php echo isset($lang['label_since'])?$lang['label_since']:'Desde:'; ?>
      </label>
      <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control"
             value="<?php echo htmlspecialchars($fechaInicio); ?>">
    </div>
    <div class="form-group mr-2">
      <label for="fecha_fin" class="mr-2">
        <?php echo isset($lang['label_until'])?$lang['label_until']:'Hasta:'; ?>
      </label>
      <input type="date" name="fecha_fin" id="fecha_fin" class="form-control"
             value="<?php echo htmlspecialchars($fechaFin); ?>">
    </div>
    <button type="submit" class="btn btn-primary">
      <?php echo isset($lang['btn_filter']) ? $lang['btn_filter'] : 'Filtrar'; ?>
    </button>
    <button type="submit" name="nav" value="prev" class="btn btn-secondary ml-2">
      <?php echo isset($lang['btn_prev_month'])?$lang['btn_prev_month']:'Mes Anterior'; ?>
    </button>
    <button type="submit" name="nav" value="next" class="btn btn-secondary ml-2">
      <?php echo isset($lang['btn_next_month'])?$lang['btn_next_month']:'Mes Siguiente'; ?>
    </button>
  </form>
  <div class="row mb-3">
    <div class="col">
      <div class="card">
        <div class="card-header">
          <?php echo isset($lang['label_saldo_inicial'])?$lang['label_saldo_inicial']:'Saldo Inicial'; ?>
          (<?php echo date('d-m-Y', strtotime($fechaInicio)); ?>)
        </div>
        <div class="card-body">
          <h3><?php echo number_format($saldoInicial,2); ?> €</h3>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card">
        <div class="card-header">
          <?php echo isset($lang['label_total_ingresos'])?$lang['label_total_ingresos']:'Total Ingresos del Período'; ?>
        </div>
        <div class="card-body">
          <h3 class="text-success"><?php echo number_format($totalIngresosPeriodo,2); ?> €</h3>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card">
        <div class="card-header">
          <?php echo isset($lang['label_total_gastos'])?$lang['label_total_gastos']:'Total Gastos del Período'; ?>
        </div>
        <div class="card-body">
          <h3 class="text-danger"><?php echo number_format($totalGastosPeriodo,2); ?> €</h3>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card">
        <div class="card-header">
          <?php echo isset($lang['label_saldo_final'])?$lang['label_saldo_final']:'Saldo Final'; ?>
          (<?php echo date('d-m-Y', strtotime($fechaFin)); ?>)
        </div>
        <div class="card-body">
          <h3 class="text-info"><?php echo number_format($saldoFinal,2); ?> €</h3>
        </div>
      </div>
    </div>
  </div>
  <h4>
    <?php echo isset($lang['label_movimientos_periodo'])?$lang['label_movimientos_periodo']:'Movimientos del período'; ?>
    <?php echo date('d-m-Y', strtotime($fechaInicio)); ?> - <?php echo date('d-m-Y', strtotime($fechaFin)); ?>
  </h4>
  <table class="table table-bordered table-hover">
    <thead class="thead-light">
      <tr>
        <th><?php echo isset($lang['label_fecha'])?$lang['label_fecha']:'Fecha'; ?></th>
        <th><?php echo isset($lang['label_concepto'])?$lang['label_concepto']:'Concepto'; ?></th>
        <th><?php echo isset($lang['label_tipo'])?$lang['label_tipo']:'Tipo'; ?></th>
        <th><?php echo isset($lang['label_yaxis'])?$lang['label_yaxis']:'Cantidad'; ?></th>
      </tr>
    </thead>
    <tbody>
    <?php if(empty($movimientosPeriodo)): ?>
      <tr>
        <td colspan="4" class="text-center">
          <?php echo isset($lang['label_no_movimientos'])?$lang['label_no_movimientos']:'No hay movimientos en este periodo.'; ?>
        </td>
      </tr>
    <?php else: ?>
      <?php usort($movimientosPeriodo,function($a,$b){return strtotime($b['fechaISO'])-strtotime($a['fechaISO']);}); ?>
      <?php foreach($movimientosPeriodo as$m): ?>
        <tr>
          <td><?php echo htmlspecialchars($m['fecha_display']); ?></td>
          <td><?php echo htmlspecialchars($m['concepto']); ?></td>
          <td><?php echo $m['tipo']==='ingreso'
            ? '<span class="text-success">'.(isset($lang['label_ingreso'])?$lang['label_ingreso']:'Ingreso').'</span>'
            : '<span class="text-danger">'.(isset($lang['label_gasto'])?$lang['label_gasto']:'Gasto').'</span>'; ?>
          </td>
          <td>
            <?php
            $c=($m['tipo']==='ingreso')?'text-success':'text-danger';
            echo "<span class='{$c}'>".number_format($m['monto'],2)." €</span>";
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="chart-container">
    <canvas id="myChart"></canvas>
  </div>
</div>
<footer class="text-center mt-4">
  <a href="https://github.com/trankten/tkzgastos" target="_blank">
    <?php echo isset($lang['repo_text'])?$lang['repo_text']:'Ver en GitHub'; ?>
  </a>
  -
  <?php
  $versionText = isset($lang['version_text'])
    ? str_replace('<VERSION>', TKZ_GASTOS_VERSION, $lang['version_text'])
    : ('TKZ Gastos '.TKZ_GASTOS_VERSION);
  echo $versionText;
  ?>
</footer>
<script>
var ctx=document.getElementById('myChart').getContext('2d');
var myChart=new Chart(ctx,{
  type:'line',
  data:{
    labels:<?php echo json_encode($labels); ?>,
    datasets:[
      {
        label:'<?php echo isset($lang['label_ingresos'])?$lang['label_ingresos']:'Ingresos'; ?>',
        data:<?php echo json_encode($ingresosArray); ?>,
        backgroundColor:'rgba(40,167,69,0.2)',
        borderColor:'rgba(40,167,69,1)',
        borderWidth:2,
        fill:true
      },
      {
        label:'<?php echo isset($lang['label_gastos'])?$lang['label_gastos']:'Gastos'; ?>',
        data:<?php echo json_encode($gastosArray); ?>,
        backgroundColor:'rgba(220,53,69,0.2)',
        borderColor:'rgba(220,53,69,1)',
        borderWidth:2,
        fill:true
      },
      {
        label:'<?php echo isset($lang['label_saldo_acumulado'])?$lang['label_saldo_acumulado']:'Saldo acumulado'; ?>',
        data:<?php echo json_encode($saldoArray); ?>,
        backgroundColor:'rgba(23,162,184,0.2)',
        borderColor:'rgba(23,162,184,1)',
        borderWidth:2,
        fill:true
      }
    ]
  },
  options:{
    responsive:true,
    scales:{y:{beginAtZero:true}}
  }
});
$('#langSelector').on('change',function(){
  var sel=$(this).val();
  var url=new URL(window.location.href);
  url.searchParams.set('locale',sel);
  window.location=url.toString();
});
</script>
</body>
</html>
