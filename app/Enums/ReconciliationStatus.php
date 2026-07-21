<?php

namespace App\Enums;

enum ReconciliationStatus: string { case EnProceso = 'en_proceso';  case Completada = 'completada';  case Fallida = 'fallida'; }
