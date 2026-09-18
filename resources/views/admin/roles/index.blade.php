@extends('layouts.app')
@section('title', 'Perfis e permissões • ETEFlow')
@section('page', 'admin-roles')
@section('content')
<div class="app-shell">@include('operation.navigation')
<main class="workspace admin-workspace">
<header class="page-header"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Perfis e permissões</h1><p>Defina o que cada perfil pode executar no backend.</p></div></header>
@if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
<div class="role-tabs">@foreach($roles as $role)<a class="{{ $selectedRole?->is($role) ? 'active' : '' }}" href="{{ route('admin.roles.index',['role'=>$role->id]) }}">{{ $role->name }}</a>@endforeach</div>
@php
$moduleLabels=['readings'=>'Leituras','actions'=>'Ações','occurrences'=>'Ocorrências','aspersions'=>'Aspersão','chemical_stock'=>'Estoque químico','users'=>'Usuários','employees'=>'Colaboradores','shifts'=>'Turnos','roles'=>'Perfis','administration'=>'Administração'];
$actionLabels=['view'=>'Visualizar','fill'=>'Preencher','complete'=>'Concluir','reopen'=>'Reabrir','edit_history'=>'Editar histórico','configure'=>'Configurar','create'=>'Criar','start'=>'Iniciar','edit'=>'Editar','cancel'=>'Cancelar','close'=>'Encerrar','convert_to_action'=>'Converter em ação','finish'=>'Finalizar','view_history'=>'Ver histórico','manage_points'=>'Gerenciar pontos','deactivate'=>'Desativar','manage_access'=>'Gerenciar acessos','block'=>'Bloquear','reset_password'=>'Redefinir senha','change_role'=>'Alterar perfil','view_sensitive'=>'Ver dados sensíveis','manage_team'=>'Gerenciar equipe','change_schedule'=>'Alterar horários','replace_operator'=>'Substituir operador','manage'=>'Gerenciar','manage_permissions'=>'Gerenciar permissões','count'=>'Registrar contagem','receive'=>'Registrar entrada','view_balance'=>'Ver saldo','view_analytics'=>'Ver análises','correct'=>'Corrigir registros'];
@endphp
@if($selectedRole)<section class="role-grid"><article class="panel-card role-card"><div class="panel-head"><div><span class="eyebrow accent">PERFIL SELECIONADO</span><h2>{{ $selectedRole->name }}</h2><p>{{ $selectedRole->permissions->where('module', '!=', 'equipment')->count() }} permissões ativas</p></div><span class="status-badge active">Ativo</span></div>
<div class="inline-alert">As alterações afetam todos os usuários deste perfil. O sistema impede remover o último administrador.</div>
<form method="POST" action="{{ route('admin.roles.update',$selectedRole) }}">@csrf @method('PUT')
@foreach($selectedRole->permissions->where('module', 'equipment') as $permission)<input type="hidden" name="permission_ids[]" value="{{ $permission->id }}">@endforeach
@foreach($permissions as $module=>$items)@continue($module === 'equipment')<fieldset class="permission-module"><legend>{{ $moduleLabels[$module] ?? ucfirst($module) }}</legend><div class="permission-checks">@foreach($items as $permission)<label><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($selectedRole->permissions->contains('id',$permission->id))><span><b>{{ $actionLabels[$permission->action] ?? str_replace('_',' ',$permission->action) }}</b><small>{{ $permission->code }}</small></span></label>@endforeach</div></fieldset>@endforeach
<button class="button primary large">Salvar permissões de {{ $selectedRole->name }}</button></form></article></section>@else<div class="empty-state">Nenhum perfil ativo encontrado.</div>@endif
</main></div>
@endsection
