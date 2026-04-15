## Clone o repositório:


```
git clone https://github.com/seu-usuario/loja-uoou.git
cd loja-uoou
```
## Instale as dependências:

```
composer install
```

## Configure as variáveis de ambiente:
Renomeie o arquivo .env para .env.local e configure suas credenciais de banco de dados e as chaves do Stripe:

```
DATABASE_URL="mysql://usuario:senha@127.0.0.1:3306/servidor?serverVersion=8.0"
STRIPE_SECRET_KEY=sua_chave_stripe
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

## Crie o banco de dados e execute as migrations:

```
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```
## Inicie o servidor local:
```
symfony serve
```

## Configuração de Administrador
Para acessar a área de gestão de produtos, seu usuário precisa da role ROLE_ADMIN. Após criar sua conta no site, execute este comando no seu banco de dados:
```
UPDATE user SET roles = '["ROLE_ADMIN"]' WHERE email = 'seu-email@exemplo.com';
```
