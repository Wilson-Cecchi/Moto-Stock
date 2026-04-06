## MotoStock — Sistema de Gestão e Previsão de Estoque

Trabalho da disciplina de **Fundamentos de Data Science – 1° Bimestre**

Sistema web de apoio à decisão para previsão de demanda e gestão de estoque de uma distribuidora de motopeças com 1 matriz e 4 filiais.

---

## Equipe

- Bruna Vitoria de Oliveira Santos
- Fernando Rafael Ramos
- Wilson Klein Cecchi

---

## Requisitos do Trabalho

| Requisito | Status |
|-----------|--------|
| 1 matriz + 4 filiais | ✅ |
| Mínimo 100 produtos por loja | ✅ 100 por loja (500 total) |
| Mínimo 10 tipos de produtos | ✅ 10 categorias por loja |
| Planejamento de 6 meses | ✅ Abr–Set 2026 |
| Planilha Excel | ✅ Exportação dinâmica pelo sistema |
| Simulação ao vivo | ✅ Admin permite registrar vendas em tempo real |

---

## Estrutura de Arquivos

```
motostock/
    |_ assets/
    |         |_ style.css
    |         |_ admin_extra.css      
    |_ includes/
    |           |_ header.php
    |           |_ footer.php
    |           |_ admin_sidebar.php 
    |_ admin/
    |        |_ auth.php
    |        |_ login.php             ← redireciona para /motostock/login.php
    |        |_ logout.php            ← redireciona para /motostock/logout.php
    |        |_ index.php
    |        |_ produtos.php
    |        |_ venda.php
    |        |_ transferencia.php
    |        |_ exportar.php
    |        |_ metas.php             
    |        |_ usuarios.php          
    |_ config.php
    |_ login.php          
    |_ logout.php         
    |_ index.php         
    |_ estoque.php        
    |_ previsao.php       
    |_ vendas.php         
    |_ relatorio.php      
    |_ setup.sql          ← Banco de dados principal
    |_ setup_v2.sql       ← Tabelas de usuários e metas (rodar após setup.sql)
    |_ setup_v3.sql       ← Tabela de solicitações e nível funcionário (rodar após setup_v2.sql)
    |_ setup_usuarios.php ← Cria usuários no banco (rodar 1x e deletar)
```

---

## Instalação

### Pré-requisitos
- [XAMPP](https://www.apachefriends.org/) instalado e rodando (Apache + MySQL)

### Passo a passo

**1. Copiar a pasta para o XAMPP**

| Sistema | Caminho |
|---------|---------|
| Windows | `C:\xampp\htdocs\motostock\` |
| macOS   | `/Applications/XAMPP/htdocs/motostock/` |
| Linux   | `/opt/lampp/htdocs/motostock/` |

**2. Importar o banco de dados**

- Abre `http://localhost/phpmyadmin`
- Clica em **Importar** → seleciona `setup.sql` → **Executar**
- Repete o processo com `setup_v2.sql` (cria tabelas `usuarios` e `metas`)
- Repete o processo com `setup_v3.sql` (cria tabela `solicitacoes` e nível `funcionario`)

**3. Criar os usuários**

- Acessa `http://localhost/motostock/setup_usuarios.php` uma única vez
- ⚠️ **Delete o arquivo após rodar** — ele não deve ficar acessível

**4. (Linux apenas) Corrigir permissões**

```bash
chmod -R 755 /opt/lampp/htdocs/motostock
```

**5. Acessar o sistema**

```
http://localhost/motostock/login.php
```

---

## Páginas do Sistema

| Página | URL | Descrição |
|--------|-----|-----------|
| Login | `/motostock/login.php` | Entrada unificada para admin e gerentes |
| Dashboard | `/motostock` | KPIs, gráficos, ranking e metas de vendas |
| Estoque | `/motostock/estoque.php` | Estoque por loja com alertas e badge na sidebar |
| Previsão | `/motostock/previsao.php` | Previsão dos próximos 6 meses |
| Vendas | `/motostock/vendas.php` | Histórico com filtros de data, ticket médio e paginação |
| Relatório | `/motostock/relatorio.php` | Relatório completo imprimível / exportável como PDF |
| Admin | `/motostock/admin` | Área administrativa (somente admin) |

---

## Login e Permissões

O sistema possui dois níveis de acesso. Todos os usuários entram pela mesma página de login.

| Usuário    | Senha         | Nível   | Acesso                                    |
|------------|---------------|---------|-------------------------------------------|
| `admin`    | `umasenhaboa` | Admin   | Tudo + área administrativa                |
| `gerente1` | `loja1234`    | Gerente | Somente Matriz — São Paulo                |
| `gerente2` | `loja1234`    | Gerente | Somente Filial 1 — Rio de Janeiro         |
| `gerente3` | `loja1234`    | Gerente | Somente Filial 2 — Belo Horizonte         |
| `gerente4` | `loja1234`    | Gerente | Somente Filial 3 — Curitiba               |
| `gerente5` | `loja1234`    | Gerente | Somente Filial 4 — Salvador               |

**Comportamento por nível:**
- **Admin** — vê dados de todas as lojas, acessa a área administrativa, edita metas
- **Gerente** — vê apenas os dados da própria loja em todas as páginas (KPIs, estoque, vendas, previsão, relatório); não acessa o admin

As senhas são armazenadas com hash **bcrypt** na tabela `usuarios` — nunca em texto plano.

### Funcionalidades do Admin
- **Gerenciar Produtos** — adicionar, editar e remover produtos de qualquer loja
- **Registrar Venda** — lançar venda ao vivo com desconto automático do estoque
- **Transferência de Estoque** — mover produtos entre lojas
- **Metas de Vendas** — definir metas mensais por loja; progresso visível no dashboard
- **Exportar Excel** — gera `.xls` com 7 abas: Dashboard, Clientes, Produtos, Vendas, Resumo por Loja, Previsão 6 Meses e Transferências
- **Usuários** — trocar senha de qualquer usuário do sistema

---

## Dados do Sistema

- **5 lojas:** Matriz São Paulo (SP), Filial 1 Rio de Janeiro (RJ), Filial 2 Belo Horizonte (MG), Filial 3 Curitiba (PR), Filial 4 Salvador (BA)
- **500 produtos** cadastrados (100 por loja, 10 categorias cada)
- **115 clientes** cadastrados distribuídos pelas 5 lojas
- **80 vendas** registradas (Jan–Mar 2026)
- **Previsão** calculada por média mensal × 6 meses

---

## Metodologia de Previsão

```
Média mensal = total vendido nos 3 meses ÷ 3
Demanda prevista = média mensal × 6 meses
Reposição sugerida = demanda prevista − estoque atual
```

### Como funciona na prática?

Imagine que um produto teve as seguintes vendas:

| Mês       | Vendas |
|-----------|--------|
| Janeiro   | 10     |
| Fevereiro | 20     |
| Março     | 30     |

**Passo 1 — Média mensal**
```
(10 + 20 + 30) ÷ 3 = 20 unidades por mês
```

**Passo 2 — Projeção dos 6 meses futuros**
```
Abril:    20 × 1 = 20
Maio:     20 × 2 = 40
Junho:    20 × 3 = 60
Julho:    20 × 4 = 80
Agosto:   20 × 5 = 100
Setembro: 20 × 6 = 120
```

**Passo 3 — Quantidade a repor**
```
Total previsto = 120 unidades
Estoque atual  = 15 unidades
Repor          = 120 - 15 = 105 unidades
```

A coluna **Repor** na página de Previsão responde a pergunta:
> *"Quantas unidades preciso comprar hoje para o estoque não zerar nos próximos 6 meses?"*

⚠️ **Limitação:** o sistema usa média simples — não considera sazonalidade nem tendência de crescimento. Para isso seriam necessários pelo menos 12 meses de histórico.

---

## 🛠️ Tecnologias

- PHP 8.2
- MySQL / MariaDB
- Chart.js 4.4
- HTML/CSS puro (sem frameworks)
