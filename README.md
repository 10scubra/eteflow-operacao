# ETEFlow Operação

Sistema web PWA para gestão operacional de estação de tratamento de efluentes, com operação mobile first e visão consolidada para o responsável Master.

## Recursos atuais

- Login separado para Master e operadores.
- Turnos diurno e noturno determinados pela hora do servidor em `America/Sao_Paulo`.
- Rodadas de leitura, parâmetros operacionais e alerta de valores fora da faixa.
- Controle de edição simultânea e resposta HTTP 409 em conflitos.
- Ações com checklist, observações e evidências fotográficas.
- Ocorrências, aspersão, passagem de turno, equipe e equipamentos.
- Dashboard Master atualizado periodicamente.
- PWA instalável com página offline básica.

## Tecnologia

- PHP 8.3+
- Laravel 13
- MySQL ou MariaDB
- JavaScript e CSS servidos pela aplicação

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Ajuste as credenciais MySQL no `.env` antes de executar as migrations. O seeder `EteFlowSeeder` é exclusivo para homologação e é bloqueado em produção.

## Hostinger

A preparação e a lista de validação estão em [HOSTINGER.md](HOSTINGER.md). Use `.env.hostinger.example` como modelo e nunca publique um arquivo `.env` real.

## Segurança dos dados

Fotos ficam no armazenamento privado da aplicação. Um backup completo precisa preservar o banco MySQL e `storage/app/private` no mesmo momento.
