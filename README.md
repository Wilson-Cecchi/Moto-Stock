# MotoStock — Sistema de Gestão e Previsão de Estoque

Trabalho da disciplina de **Fundamentos de Data Science – 1° Bimestre**

Sistema web de apoio à decisão para previsão de demanda e gestão de estoque de uma distribuidora de motopeças com 1 matriz e 4 filiais em 5 estados.

---

## 👥 Equipe

- Bruna Vitoria de Oliveira Santos
- Fernando Rafael Ramos
- Wilson Klein Cecchi

---

## 📋 Requisitos do Trabalho

| Requisito | Status |
|-----------|--------|
| 1 matriz + 4 filiais em 5 estados diferentes | ✅ |
| Mínimo 100 produtos por loja | ✅ 100 por loja (500 total) |
| Mínimo 10 tipos de produtos por loja | ✅ 10 categorias |
| Planejamento de 6 meses | ✅ Abr–Set 2025 |
| Planilha Excel | ✅ Exportação dinâmica com 8 abas |
| Dashboard com dados financeiros | ✅ |
| Número de clientes | ✅ |
| Quantidade de produtos vendidos no mês | ✅ |
| Quantidade de vendas | ✅ |
| Receita por estado | ✅ |
| Receita total | ✅ |
| Receita total por filial | ✅ |
| Receita total por produto | ✅ |
| Simulação ao vivo | ✅ Admin permite registrar vendas em tempo real |

---

## 🗂️ Estrutura de Arquivos

```
motostock/
├── assets/
│   ├── style.css             ← estilos globais + responsividade mobile
│   └── admin_extra.css       ← estilos exclusivos da área admin
├── includes/
│   ├── header.php            ← HTML, sidebar, topbar e hamburger (páginas públicas)
│   ├── footer.php
│   └── admin_sidebar.php     ← sidebar reutilizável do admin (com hamburger mobile)
├── admin/
│   ├── auth.php
│   ├── index.php             ← Painel administrativo
│   ├── produtos.php          ← CRUD de produtos
│   ├── venda.php             ← Registrar venda ao vivo
│   ├── transferencia.php     ← Transferência de estoque entre lojas
│   ├── exportar.php          ← Geração do Excel (.xls com 8 abas)
│   ├── metas.php             ← Metas mensais por loja
│   ├── solicitacoes.php      ← Aprovar/rejeitar solicitações (admin)
│   └── usuarios.php          ← Gerenciar senhas dos usuários
├── gerente/
│   └── solicitacoes.php      ← Aprovar solicitações (gerente da loja cedente)
├── config.php                ← Conexão com BD e helpers globais
├── login.php                 ← Login unificado (todos os níveis)
├── logout.php
├── index.php                 ← Dashboard (KPIs, gráficos, metas)
├── estoque.php               ← Estoque por loja com alertas
├── previsao.php              ← Previsão de demanda — próximos 6 meses
├── vendas.php                ← Histórico de vendas com filtros e paginação
├── relatorio.php             ← Relatório imprimível / PDF
├── funcionario.php           ← Tela do funcionário (solicitações de reposição)
├── setup.sql                 ← Banco de dados principal
├── setup_v2.sql              ← Tabelas: usuarios, metas, transferencias
├── setup_v3.sql              ← Tabela: solicitacoes + nível funcionario
└── setup_usuarios.php        ← Cria usuários no banco (rodar 1x e deletar)
```

---

## ⚙️ Instalação

### Pré-requisitos
- [XAMPP](https://www.apachefriends.org/) instalado e rodando (Apache + MySQL)

### Passo a passo

**1. Copiar a pasta para o XAMPP**

| Sistema | Caminho |
|---------|---------|
| Windows | `C:\xampp\htdocs\motostock\` |
| macOS | `/Applications/XAMPP/htdocs/motostock/` |
| Linux | `/opt/lampp/htdocs/motostock/` |

**2. Importar o banco de dados**

Abre `http://localhost/phpmyadmin`, clica em **Importar** e executa os arquivos na ordem:

1. `setup.sql` — cria o banco com lojas, produtos, clientes e vendas
2. `setup_v2.sql` — cria as tabelas `usuarios`, `metas` e `transferencias`
3. `setup_v3.sql` — cria a tabela `solicitacoes` e adiciona o nível `funcionario`

**3. Criar os usuários**

Acessa `http://localhost/motostock/setup_usuarios.php` uma única vez.

> ⚠️ **Delete o arquivo após rodar** — ele não deve ficar acessível.

**4. (Linux apenas) Corrigir permissões**

```bash
chmod -R 755 /opt/lampp/htdocs/motostock
```

**5. Acessar o sistema**

```
http://localhost/motostock/login.php
```

---

## 🖥️ Páginas do Sistema

| Página | URL | Acesso |
|--------|-----|--------|
| Login | `/motostock/login.php` | Todos |
| Dashboard | `/motostock/` | Admin, Gerente |
| Estoque | `/motostock/estoque.php` | Admin, Gerente |
| Previsão 6 meses | `/motostock/previsao.php` | Admin, Gerente |
| Vendas | `/motostock/vendas.php` | Admin, Gerente |
| Relatório PDF | `/motostock/relatorio.php` | Admin, Gerente |
| Solicitações (gerente) | `/motostock/gerente/solicitacoes.php` | Gerente |
| Painel do Funcionário | `/motostock/funcionario.php` | Funcionário |
| Área Admin | `/motostock/admin/` | Admin |

---

## 🔐 Login e Permissões

O sistema possui três níveis de acesso. Todos entram pela mesma página de login.

| Usuário | Senha | Nível | Acesso |
|---------|-------|-------|--------|
| `admin` | `umasenhaboa` | Admin | Tudo + área administrativa |
| `gerente1` | `loja1234` | Gerente | Somente Matriz — São Paulo |
| `gerente2` | `loja1234` | Gerente | Somente Filial 1 — Rio de Janeiro |
| `gerente3` | `loja1234` | Gerente | Somente Filial 2 — Belo Horizonte |
| `gerente4` | `loja1234` | Gerente | Somente Filial 3 — Curitiba |
| `gerente5` | `loja1234` | Gerente | Somente Filial 4 — Salvador |
| `func1`–`func5` | `func1234` | Funcionário | Somente solicitações da própria loja |

**Comportamento por nível:**
- **Admin** — acesso total: todas as lojas, área administrativa, metas, usuários e exportação
- **Gerente** — vê apenas dados da própria loja; aprova solicitações de transferência recebidas; não acessa o admin
- **Funcionário** — acessa apenas a tela de solicitações (reposição de estoque ou transferência entre lojas)

As senhas são armazenadas com hash **bcrypt** — nunca em texto plano.

### Funcionalidades da Área Admin

- **Produtos** — adicionar, editar e remover produtos de qualquer loja
- **Nova Venda** — lançar venda ao vivo com desconto automático do estoque
- **Transferência** — mover produtos entre lojas com histórico persistente
- **Metas** — definir metas mensais por loja; progresso exibido no dashboard
- **Solicitações** — aprovar ou rejeitar pedidos de reposição e transferência
- **Exportar Excel** — gera `.xls` com 8 abas (detalhes abaixo)
- **Usuários** — trocar senha de qualquer usuário do sistema

---

## 📊 Dados do Sistema

| Item | Quantidade |
|------|-----------|
| Lojas | 5 (1 Matriz + 4 Filiais) |
| Estados | SP, RJ, MG, PR, BA |
| Produtos | 500 (100 por loja) |
| Categorias | 10 (Motor, Freios, Suspensão, Elétrica, Customização, Proteção, Vestuário, Manutenção, Acessório, Segurança) |
| Clientes | 115 distribuídos pelas 5 lojas |
| Vendas registradas | 80 (Jan–Mar 2025) |

---

## 📁 Planilha Excel — 8 Abas

Gerada em `Admin → Exportar Excel`.

| # | Aba | Conteúdo |
|---|-----|----------|
| 1 | **Lojas** | Matriz e filiais com cidade, estado e endereço |
| 2 | **Produtos** | Catálogo completo com preço, estoque atual e valor em estoque |
| 3 | **Previsão 6 Meses** | Média mensal, projeção mês a mês, quantidade a repor e receita prevista |
| 4 | **Dashboard** | Todos os KPIs: clientes, vendas, receita por mês, por estado, por filial e por produto |
| 5 | **Resumo por Loja** | Nº de vendas, quantidade total, receita e ticket médio por loja |
| 6 | **Vendas** | Histórico completo com cliente, produto, quantidade e valor |
| 7 | **Clientes** | Lista de clientes com e-mail e loja vinculada |
| 8 | **Transferências** | Histórico de transferências de estoque entre lojas |

---

## 🧮 Metodologia de Previsão

```
Média mensal       = total vendido nos 3 meses ÷ 3
Demanda prevista   = média mensal × 6 meses
Reposição sugerida = demanda prevista − estoque atual
```

**Exemplo prático:**

| Mês | Vendas |
|-----|--------|
| Janeiro | 10 |
| Fevereiro | 20 |
| Março | 30 |

```
Média mensal = (10 + 20 + 30) ÷ 3 = 20 unidades/mês

Projeção:
  Abril     → 20 × 1 =  20
  Maio      → 20 × 2 =  40
  Junho     → 20 × 3 =  60
  Julho     → 20 × 4 =  80
  Agosto    → 20 × 5 = 100
  Setembro  → 20 × 6 = 120

Estoque atual = 15
Repor = 120 − 15 = 105 unidades
```

A coluna **Repor** responde: *"Quantas unidades preciso comprar hoje para o estoque não zerar nos próximos 6 meses?"*

> ⚠️ O modelo usa média móvel simples — não considera sazonalidade nem tendência de crescimento. Para maior precisão, seriam necessários ao menos 12 meses de histórico.

---

## 🛠️ Tecnologias

| Tecnologia | Uso |
|-----------|-----|
| PHP 8.2 | Backend e lógica de negócio |
| MySQL / MariaDB | Banco de dados |
| Chart.js 4.4 | Gráficos interativos |
| SpreadsheetML | Geração do arquivo Excel (.xls) |
| HTML/CSS puro | Interface (sem frameworks) |
| bcrypt | Hash de senhas |