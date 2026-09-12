# Implantação do ETEFlow na Hostinger Business

Este roteiro mantém separados o computador de desenvolvimento, a homologação e o futuro ambiente hospedado.

## Requisitos

- Selecionar PHP 8.3 ou 8.4 no hPanel e também no terminal SSH.
- Usar Composer 2 (`composer2` na Hostinger).
- Criar uma base MySQL e um usuário exclusivos.
- Ativar HTTPS, necessário para a PWA fora de `localhost`.
- Garantir escrita em `storage` e `bootstrap/cache`.
- Publicar somente a pasta `public` como raiz acessível pela web sempre que a configuração do plano permitir.

O Laravel 13 deste projeto não funciona com PHP 8.1.

## Configuração

Copie `.env.hostinger.example` para `.env` somente no servidor. Substitua domínio, banco, usuário e senha. Depois gere o `APP_KEY` uma única vez e guarde-o nos backups.

Nunca envie para o GitHub o `.env`, o banco local, as fotos operacionais ou credenciais reais.

## Primeira instalação

Na raiz da aplicação:

```bash
composer2 install --no-dev --prefer-dist --optimize-autoloader
cp .env.hostinger.example .env
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
```

Não execute `php artisan db:seed` em produção. O seeder atual contém apenas a estrutura demonstrativa de homologação e a aplicação impede sua execução quando `APP_ENV=production`.

## Estrutura pública

O arquivo `public/.htaccess` já possui as regras do Laravel para Apache/LiteSpeed. O ideal é apontar a raiz do domínio para `public`. Caso o hPanel exija instalação em `public_html`, siga o método Laravel indicado pela Hostinger sem expor `.env`, `vendor`, `storage` ou o restante do código diretamente por URL.

## Banco e arquivos

Use no `.env` os nomes completos exibidos no hPanel, inclusive o prefixo da conta. O host costuma ser `localhost`, mas prevalece o valor informado pela Hostinger.

Os registros ficam no MySQL. As fotos de evidências e ocorrências ficam em `storage/app/private`. Faça backup dos dois em conjunto.

## Atualizações

Antes de cada atualização, faça backup do MySQL e de `storage/app/private`:

```bash
php artisan down --secret="endereco-temporario-aleatorio"
composer2 install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

Use `composer install` com o `composer.lock` testado. Não execute `composer update` diretamente em produção.

## Filas e agendador

A configuração inicial usa `QUEUE_CONNECTION=sync`, compatível com o plano Business sem processo permanente. Hoje os turnos e rodadas são garantidos quando a aplicação é acessada, então não há cron obrigatório.

Quando surgirem tarefas agendadas, configure no hPanel `php artisan schedule:run`. Os cron jobs da Hostinger usam UTC; a aplicação continua exibindo a operação em `America/Sao_Paulo`.

## Validação antes do domínio principal

1. Instalar primeiro em domínio temporário ou subdomínio.
2. Abrir `/up` e confirmar que a aplicação responde.
3. Confirmar `APP_ENV=production` e `APP_DEBUG=false`.
4. Criar usuários reais e garantir que usuários demonstrativos estejam desativados ou ausentes.
5. Testar login, logout, leituras e conflitos com dois celulares.
6. Conferir data, hora, turno diurno e turno noturno.
7. Testar fotos de evidências e ocorrências.
8. Instalar a PWA em um celular via HTTPS.
9. Verificar `storage/logs` e configurar backups no hPanel.
10. Somente depois apontar o domínio definitivo.
