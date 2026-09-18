<?php

use App\Http\Controllers\AspersionPointController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChemicalStockAdminController;
use App\Http\Controllers\ChemicalStockController;
use App\Http\Controllers\ChemicalStockCorrectionController;
use App\Http\Controllers\DosingAssistantController;
use App\Http\Controllers\EmployeeAccessController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\IndicatorAdminController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\OperationsModuleController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PublicAspersionController;
use App\Http\Controllers\PublicIndicatorController;
use App\Http\Controllers\ReadingSectionController;
use App\Http\Controllers\ReadingTemplateController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ShiftTeamController;
use Illuminate\Support\Facades\Route;

Route::get('/shared/indicators/{token}', [PublicIndicatorController::class, 'show'])->middleware('throttle:120,1')->name('public.indicators.show');
Route::get('/aspersao/acesso/{token}', [PublicAspersionController::class, 'show'])->name('aspersion.public.show');
Route::post('/aspersao/acesso/{token}', [PublicAspersionController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('aspersion.public.store');
Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->role === 'master' ? 'master' : 'operation.home')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/definir-senha', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/definir-senha', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/operacao', [OperationsModuleController::class, 'home'])->name('operation.home');
    Route::get('/leituras', [OperationController::class, 'readings'])->name('readings');
    Route::get('/master', [OperationController::class, 'master'])->name('master');
    Route::get('/dosagem', [DosingAssistantController::class, 'index'])->name('dosing.index');
    Route::get('/indicadores', [IndicatorController::class, 'index'])->name('indicators.index');
    Route::get('/laboratorio', [LaboratoryController::class, 'index'])->name('laboratory.index');
    Route::get('/laboratorio/nova-coleta', [LaboratoryController::class, 'create'])->name('laboratory.create');
    Route::post('/laboratorio/coletas', [LaboratoryController::class, 'store'])->name('laboratory.store');
    Route::post('/laboratorio/resultados/{result}/correcoes', [LaboratoryController::class, 'correct'])->name('laboratory.results.correct');
    Route::get('/estoque-quimicos', [ChemicalStockController::class, 'dashboard'])->name('chemical-stock.dashboard');
    Route::get('/estoque-quimicos/conferencia', [ChemicalStockController::class, 'countForm'])->name('chemical-stock.count.form');
    Route::post('/estoque-quimicos/conferencia', [ChemicalStockController::class, 'count'])->name('chemical-stock.count.store');
    Route::get('/estoque-quimicos/entrada', [ChemicalStockController::class, 'receiptForm'])->name('chemical-stock.receipt.form');
    Route::post('/estoque-quimicos/entrada', [ChemicalStockController::class, 'receipt'])->name('chemical-stock.receipt.store');
    Route::get('/estoque-quimicos/historico', [ChemicalStockController::class, 'history'])->name('chemical-stock.history');
    Route::get('/estoque-quimicos/movimentacoes', [ChemicalStockController::class, 'operations'])->name('chemical-stock.operations');
    Route::post('/estoque-quimicos/retiradas', [ChemicalStockController::class, 'issue'])->name('chemical-stock.issue.store');
    Route::post('/estoque-quimicos/transferencias', [ChemicalStockController::class, 'transfer'])->name('chemical-stock.transfer.store');
    Route::post('/estoque-quimicos/inventarios', [ChemicalStockController::class, 'inventory'])->name('chemical-stock.inventory.store');
    Route::post('/estoque-quimicos/embalagens/{openPackage}/pesagens', [ChemicalStockController::class, 'weigh'])->name('chemical-stock.open-packages.weigh');
    Route::post('/estoque-quimicos/correcoes', [ChemicalStockCorrectionController::class, 'store'])->name('chemical-stock.corrections.store');
    Route::post('/dosagem/alertas/{alert}/iniciar', [DosingAssistantController::class, 'start'])->name('dosing.start');
    Route::post('/dosagem/alertas/{alert}/impedimento', [DosingAssistantController::class, 'impediment'])->name('dosing.impediment');
    Route::post('/dosagem/ciclos/{cycle}/percentual', [DosingAssistantController::class, 'percentage'])->name('dosing.percentage');
    Route::post('/dosagem/ciclos/{cycle}/ph', [DosingAssistantController::class, 'ph'])->name('dosing.ph');
    Route::post('/dosagem/ciclos/{cycle}/continuar', [DosingAssistantController::class, 'continue'])->name('dosing.continue');
    Route::post('/dosagem/ciclos/{cycle}/finalizar', [DosingAssistantController::class, 'finish'])->name('dosing.finish');

    Route::get('/acoes/{action?}', [OperationsModuleController::class, 'actions'])->name('actions.show');
    Route::post('/acoes', [OperationsModuleController::class, 'createAction'])->name('actions.create');
    Route::post('/acoes/{action}/iniciar', [OperationsModuleController::class, 'startAction'])->name('actions.start');
    Route::post('/acoes/{action}/concluir', [OperationsModuleController::class, 'completeAction'])->name('actions.complete');
    Route::get('/evidencias/{evidence}', [OperationsModuleController::class, 'evidence'])->name('evidences.show');

    Route::get('/aspersao', [OperationsModuleController::class, 'aspersions'])->name('aspersion');
    Route::post('/aspersao', [OperationsModuleController::class, 'startAspersion'])->name('aspersion.start');
    Route::post('/aspersao/{aspersion}/finalizar', [OperationsModuleController::class, 'endAspersion'])->name('aspersion.end');
    Route::post('/aspersao/pontos', [AspersionPointController::class, 'store'])->name('aspersion-points.store');
    Route::get('/aspersao/pontos/{aspersionPoint}/placa', [AspersionPointController::class, 'plate'])->name('aspersion-points.plate');

    Route::get('/ocorrencias', [OperationsModuleController::class, 'occurrences'])->name('occurrences');
    Route::post('/ocorrencias', [OperationsModuleController::class, 'createOccurrence'])->name('occurrences.create');
    Route::post('/ocorrencias/{occurrence}/acao', [OperationsModuleController::class, 'convertOccurrence'])->name('occurrences.convert');
    Route::get('/ocorrencias/{occurrence}/foto', [OperationsModuleController::class, 'occurrencePhoto'])->name('occurrences.photo');

    Route::get('/passagem-turno', [OperationsModuleController::class, 'handover'])->name('handover');
    Route::post('/passagem-turno', [OperationsModuleController::class, 'closeHandover'])->name('handover.close');
    Route::get('/mais', [OperationsModuleController::class, 'more'])->name('more');

    Route::get('/equipe', [ShiftTeamController::class, 'index'])->name('team.index');
    Route::put('/equipe', [ShiftTeamController::class, 'update'])->name('team.update');
    Route::post('/equipe/participantes', [ShiftTeamController::class, 'join'])->name('team.join');
    Route::put('/equipe/participantes/{shiftMember}/saida', [ShiftTeamController::class, 'leave'])->name('team.leave');
    Route::post('/equipe/participantes/{shiftMember}/substituir', [ShiftTeamController::class, 'substitute'])->name('team.substitute');

    Route::prefix('administracao')->name('admin.')->group(function () {
        Route::get('/indicadores', [IndicatorAdminController::class, 'index'])->name('indicators.index');
        Route::post('/indicadores/parametros-laboratorio', [IndicatorAdminController::class, 'parameter'])->name('indicators.parameters.store');
        Route::post('/indicadores/pontos-coleta', [IndicatorAdminController::class, 'point'])->name('indicators.points.store');
        Route::post('/indicadores/paginas', [IndicatorAdminController::class, 'page'])->name('indicators.pages.store');
        Route::post('/indicadores/indicadores', [IndicatorAdminController::class, 'indicator'])->name('indicators.store');
        Route::post('/indicadores/compartilhamentos', [IndicatorAdminController::class, 'share'])->name('indicators.shares.store');
        Route::put('/indicadores/compartilhamentos/{share}/revogar', [IndicatorAdminController::class, 'revoke'])->name('indicators.shares.revoke');
        Route::post('/indicadores/precos', [IndicatorAdminController::class, 'price'])->name('indicators.prices.store');
        Route::get('/estoque-quimicos', [ChemicalStockAdminController::class, 'index'])->name('chemical-stock.index');
        Route::post('/estoque-quimicos/unidades', [ChemicalStockAdminController::class, 'unit'])->name('chemical-stock.units.store');
        Route::post('/estoque-quimicos/produtos', [ChemicalStockAdminController::class, 'product'])->name('chemical-stock.products.store');
        Route::put('/estoque-quimicos/produtos/{product}', [ChemicalStockAdminController::class, 'updateProduct'])->name('chemical-stock.products.update');
        Route::post('/estoque-quimicos/locais', [ChemicalStockAdminController::class, 'location'])->name('chemical-stock.locations.store');
        Route::put('/estoque-quimicos/locais/{location}', [ChemicalStockAdminController::class, 'updateLocation'])->name('chemical-stock.locations.update');
        Route::get('/assistente-dosagem', [DosingAssistantController::class, 'configure'])->name('dosing.configure');
        Route::put('/assistente-dosagem', [DosingAssistantController::class, 'updateConfiguration'])->name('dosing.update');
        Route::get('/colaboradores', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/colaboradores/novo', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/colaboradores', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/colaboradores/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/colaboradores/{employee}/editar', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/colaboradores/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::get('/colaboradores/{employee}/acesso', [EmployeeAccessController::class, 'show'])->name('employees.access.show');
        Route::post('/colaboradores/{employee}/acesso', [EmployeeAccessController::class, 'store'])->name('employees.access.store');
        Route::put('/colaboradores/{employee}/acesso/bloquear', [EmployeeAccessController::class, 'block'])->name('employees.access.block');
        Route::put('/colaboradores/{employee}/acesso/desbloquear', [EmployeeAccessController::class, 'unblock'])->name('employees.access.unblock');
        Route::put('/colaboradores/{employee}/acesso/desativar', [EmployeeAccessController::class, 'deactivate'])->name('employees.access.deactivate');
        Route::put('/colaboradores/{employee}/acesso/ativar', [EmployeeAccessController::class, 'activate'])->name('employees.access.activate');
        Route::put('/colaboradores/{employee}/acesso/senha', [EmployeeAccessController::class, 'resetPassword'])->name('employees.access.password');
        Route::put('/colaboradores/{employee}/acesso/perfil', [EmployeeAccessController::class, 'role'])->name('employees.access.role');
        Route::put('/colaboradores/{employee}/acesso/permissoes/{permission}', [EmployeeAccessController::class, 'override'])->name('employees.access.override');
        Route::get('/perfis', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::put('/perfis/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
        Route::get('/construtor-leituras', [ReadingTemplateController::class, 'index'])->name('reading-templates.index');
        Route::post('/construtor-leituras', [ReadingTemplateController::class, 'store'])->name('reading-templates.store');
        Route::post('/construtor-leituras/{readingTemplate}/versoes', [ReadingTemplateController::class, 'createVersion'])->name('reading-templates.versions.store');
        Route::get('/construtor-leituras/versoes/{readingTemplateVersion}', [ReadingTemplateController::class, 'edit'])->name('reading-templates.edit');
        Route::post('/construtor-leituras/versoes/{readingTemplateVersion}/secoes', [ReadingTemplateController::class, 'storeSection'])->name('reading-templates.sections.store');
        Route::put('/construtor-leituras/secoes/{readingSectionDefinition}', [ReadingTemplateController::class, 'updateSection'])->name('reading-templates.sections.update');
        Route::post('/construtor-leituras/secoes/{readingSectionDefinition}/parametros', [ReadingTemplateController::class, 'storeParameter'])->name('reading-templates.parameters.store');
        Route::put('/construtor-leituras/parametros/{parameterRule}', [ReadingTemplateController::class, 'updateParameter'])->name('reading-templates.parameters.update');
        Route::post('/construtor-leituras/versoes/{readingTemplateVersion}/duplicar', [ReadingTemplateController::class, 'duplicate'])->name('reading-templates.duplicate');
        Route::post('/construtor-leituras/versoes/{readingTemplateVersion}/publicar', [ReadingTemplateController::class, 'publish'])->name('reading-templates.publish');
        Route::get('/construtor-leituras/versoes/{readingTemplateVersion}/preview', [ReadingTemplateController::class, 'preview'])->name('reading-templates.preview');
    });

    Route::prefix('api')->group(function () {
        Route::get('/operation-snapshot', [OperationController::class, 'snapshot']);
        Route::get('/reading-sections/{readingSection}', [ReadingSectionController::class, 'show']);
        Route::post('/reading-sections/{readingSection}/open', [ReadingSectionController::class, 'open']);
        Route::put('/reading-sections/{readingSection}', [ReadingSectionController::class, 'update']);
    });
});
