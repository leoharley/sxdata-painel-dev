# Formulários estruturados

São questionários com:
- **domicílio e moradores**: uma lista de moradores e um bloco de questões repetido para cada pessoa;
- **campos calculados**, como a renda total e a per capita;
- **pulos**, **opções filtradas** por outra questão e **alertas**.

O editor de questionários comum não muda: os estruturados têm tela própria em **Formulários estruturados**, no menu do painel. O plano está em `docs/PLANO_EVOLUCAO_GERDAU_SRA.md`.

| Onde | O quê |
|---|---|
| `application/libraries/Expressao.php` | Linguagem de expressões |
| `application/libraries/Estrutura_compilador.php` | Formato-texto → definição JSON |
| `application/libraries/Estrutura_motor.php` | Visibilidade, cálculos, validação e alertas |
| `application/libraries/Estrutura_envio.php` | Conferência do envio do app |
| `application/controllers/Estruturados.php` | Telas do painel (`formularios-estruturados/...`) |
| `application/controllers/api/Estruturado.php` | API do app |
| `sql/estruturado_migration.sql` | Tabelas |
| `docs/formularios/*.txt` | Formulários da JMC (Geral e Pequenos Negócios) |
| `tests/fixtures/expressoes.json` | Casos que valem **também para o motor do app (Dart)** |

## 1. Fluxo no painel

1. **Novo formulário:** em branco ou a partir de um modelo da JMC. Ele nasce **pausado**.
2. **Editor:** o formulário é editado como texto. A validação acontece enquanto se digita, aponta a linha de cada erro e mostra a estrutura (etapas e número de questões). Ctrl+S salva o rascunho.
3. **Prévia:** preenche o formulário no navegador com as regras reais (o motor do servidor), mostrando o que aparece, o que é calculado, as pendências e os alertas. Nada é gravado.
4. **Publicar versão:** compila o rascunho e cria a versão N. Cada entrevista guarda a versão em que foi coletada.
5. **Configurações:** projeto, status (**Ativo** vai para o app) e aplicadores.
6. **Entrevistas:** lista, detalhe (domicílio, moradores, calculados, alertas, fila de revisão) e **exportação Excel** com as abas Domicílios, Moradores e Dicionário.

## 2. Formato-texto

```
# titulo: Nome do formulário
# descricao: ...
// comentário (ignorado)

@domicilio Título da etapa            perguntas feitas uma vez
== BLOCO A - ...                      título de bloco (só exibição)
-- A.1 Seção                          título de seção (só exibição)

1. Texto da questão? [unica]
  ajuda: Técnico: texto de apoio ao entrevistador.
  se: q27 == "Não"                    exibir se
  - Sim
  - Não

@moradores 17. Qual é o nome de cada pessoa que mora no domicílio?
  ajuda: ...
  respondente: q12                    o 1º morador é o respondente (nome da q12)
  declarado: q15                      alerta se a lista tiver outro tamanho
  regra: contar_moradores(q19 == "Chefe") == 0 | Mensagem | bloqueia

@morador Questões sobre cada morador  repetidas para cada morador
  var idade = idade(q21, q5)          variável calculada oculta

18. Qual é o gênero de [NOME]? [unica]   [NOME] = nome do morador
```

**Tipos** (entre colchetes):

| Tipo | Resposta | Observação |
|---|---|---|
| `unica` | código da opção | radio |
| `multipla` | lista de códigos | caixas de marcar |
| `texto`, `texto_longo` | texto | |
| `inteiro`, `decimal`, `moeda` | número | não negativos |
| `data` | `AAAA-MM-DD` | |
| `hora` | `HH:MM` | |
| `telefone` | texto | 10 ou 11 dígitos |
| `cpf` | texto | 11 dígitos; dígito verificador inválido gera alerta |
| `cnpj` | texto | 14 dígitos |
| `composto` | objeto | ex.: `{"anos": 2, "meses": 3}`; `campos: anos:inteiro:anos, meses:inteiro:meses` |
| `lista` | lista de textos | `[lista itens=3]` |
| `calculado` | o app calcula | `formula:`; somente leitura |
| `foto` | nome do arquivo enviado | vai também para `photo_path` |
| `coordenadas` | `{"lat": -11.1, "lng": -40.5}` | vai também para latitude/longitude |
| `mensagem` | nenhuma | só texto |

**Marcas:**

| Marca | Efeito |
|---|---|
| `nsnr` | Permite marcar NS/NR num campo de digitar |
| `opcional` | Não obrigatória (o padrão é obrigatória) |
| `oculta` | Não aparece na tela |
| `auto=data`, `auto=hora` | Preenche a data ou a hora ao abrir a entrevista (editável) |
| `auto=hora_fim` | Preenche a hora ao finalizar |
| `formato=moeda`, `formato=duracao`, ... | Formato do calculado (duração em minutos) |
| `destino=foto`, `destino=coordenadas` | Copia a resposta para o cabeçalho da entrevista |

**Opções:**
- **Código:** cada linha `- Texto` vira uma opção. O código é o texto sem acentos, por exemplo "Cônjuge/companheiro(a)" vira `conjuge_companheiro_a`.
- **Complemento:** é detectado sozinho em "Sim. Quantas:" (inteiro), "Outros. Qual?" e "Outra (especificar)" (texto). Para desligar, use `{sem_complemento}`; para deixá-lo opcional, `{complemento_opcional}`.
- **Exclusiva:** "Nenhum", "NS/NR", "Não", "Ninguém", "Nada falta" etc. são exclusivas na múltipla escolha. Para marcar outra, use `{exclusiva}`.
- **Sempre disponível:** NS/NR fica sempre disponível em `opcoes_de`.
- **Outras marcas:** `{valor=codigo}` fixa o código; `{sempre}` mantém a opção sempre disponível.

**Atributos de questão:**

| Atributo | Para quê |
|---|---|
| `se: condição` | Exibir se |
| `pula: condição -> 121` | Esconde as questões entre esta e a 121 |
| `alerta: condição \| mensagem` | Aviso ao entrevistador, que não bloqueia |
| `formula: expressão` | Campo calculado |
| `opcoes_de: q143` | Mostra só as opções marcadas na q143 |
| `complemento: Rótulo (opcional)` | Campo de texto extra da questão |
| `validacao: anos_virgula` | Anos de 4 dígitos separados por vírgula |
| `codigo: nome` | Troca o código padrão `qN` |

## 3. Linguagem de expressões

A mesma linguagem serve para `se`, `pula`, `alerta`, `regra` e `formula`. **No formato-texto**, compare com o **texto** da opção: o compilador troca pelo código. Na definição JSON (o que o app recebe), as comparações já usam os códigos.

| | |
|---|---|
| Comparação | `== != > >= < <=` |
| Listas | `q48 em ["a", "b"]`, `q152 fora_de ["x"]` |
| Múltipla escolha | `q87 contem "x"`; `q33 == "x"` também significa "foi marcada" |
| Prefixo | `q57 comeca "Sim"` (opções "Sim, ...") |
| Lógica | `e`, `ou`, `nao (...)`, parênteses |
| Funções | `preenchido(q)`, `vazio(q)`, `nsnr(q)`, `tem_valor(q)` (preenchido e não NS/NR), `contar(q)` |
| Contas | `+ - * /`, `soma(...)`, `min`, `max`, `arred(x, 2)`, `div(a, b)` (vazio se b = 0), `se(cond, a, b)` |
| Datas | `idade(data_nascimento, data_ref)`, `duracao("HH:MM", "HH:MM")` em minutos |
| Moradores | `soma_moradores(expr, filtro)`, `contar_moradores(filtro)`, `existe_morador(filtro)`, `todos_moradores(filtro)` |

**Valores:**
- **Pergunta não respondida ou não aplicável (oculta):** vale **nulo**.
- **Nulo na conta:** vale 0.
- **Nulo na comparação:**
  - `== x` é falso e `!= x` é verdadeiro;
  - `em` é falso e `fora_de` é verdadeiro;
  - `>` e `<` são falsos.
- **NS/NR:** na conta vale 0, mas **marca o resultado como incompleto** (ex.: renda incompleta). A marca passa adiante para quem usar o resultado. `preenchido(q)` é verdadeiro e `tem_valor(q)` é falso.
- **Escopo:**
  - **morador:** enxerga as questões dele e as do domicílio.
  - **domicílio:** só enxerga questões de morador por `soma_moradores` e funções afins (o compilador recusa o resto).

## 4. Definição JSON (o que o app recebe)

```json
{
  "schema": 1, "titulo": "...", "descricao": "...", "questionario_id": 12, "versao": 3,
  "etapas": [
    {"tipo": "perguntas", "nivel": "domicilio", "titulo": "...", "perguntas": [P, ...]},
    {"tipo": "moradores", "numero": "17", "texto": "...", "ajuda": "...", "rotulo": "Morador",
     "respondente": "q12", "declarado": "q15",
     "regras": [{"se": "(contar_moradores((q19 == \"chefe\")) == 0)", "mensagem": "...", "bloqueia": true}]},
    {"tipo": "perguntas", "nivel": "morador", "titulo": "...", "perguntas": [P, ...]}
  ]
}
```

Pergunta P (os campos ausentes têm valor padrão):

```json
{"codigo": "q53", "numero": "53", "texto": "... [NOME] ...", "tipo": "moeda", "obrigatoria": true,
 "nsnr": true, "ajuda": "...", "bloco": "BLOCO B - ...", "secao": "B.4 Trabalho e renda",
 "exibir_se": "(preenchido(q49) e (q49 != \"trabalhador_a_familiar_auxiliar...\"))",
 "pular": {"se": "...", "para": "q121"}, "alertas": [{"se": "...", "mensagem": "..."}],
 "opcoes": [{"valor": "sim", "texto": "Sim. Quantas:", "exclusiva": false, "sempre": false,
             "complemento": {"tipo": "inteiro", "rotulo": "Quantas", "obrigatorio": true}}],
 "opcoes_de": "q143", "formula": "...", "formato": "moeda", "oculta": false, "auto": "data",
 "campos": [{"codigo": "anos", "rotulo": "anos", "tipo": "inteiro"}], "itens": 3,
 "complemento": {"tipo": "texto", "rotulo": "Locais citados", "obrigatorio": false},
 "validacao": "anos_virgula", "destino": "foto"}
```

**Regras de cada questão:**
- **Visível:** quando `exibir_se` é verdadeira (ou não existe) **e** nenhuma questão anterior visível do mesmo nível tem um `pular` verdadeiro cujo destino vem depois dela.
- **Obrigatória:** quando visível, `obrigatoria` diferente de false, e o tipo não é `calculado` nem `mensagem`, nem a questão é `oculta`.
- **Complemento de opção:** é obrigatório quando a opção está marcada, salvo `"obrigatorio": false`.
- **Opção exclusiva:** marcá-la desmarca as outras, e marcar outra desmarca a exclusiva.
- **Calculado:** é recalculado a cada mudança.
  - `formato` `moeda` e `decimal`: arredonda a 2 casas.
  - `formato` `inteiro` e `duracao`: arredonda para inteiro.
  - `formato` `auto`: mantém o tipo.
- **Finalizar** só é permitido sem erros: obrigatórias, tipos, complementos e regras com `bloqueia`. Alertas não impedem.

## 5. API

Autenticação: `Authorization: Bearer <token>`, o mesmo token de `api/auth/login`.

### `GET api/estruturado/formularios`

Devolve os formulários estruturados **publicados e ativos** que o aplicador pode coletar:

```json
{"success": true, "schema": 1, "total": 2, "data": [
  {"id": 12, "titulo": "...", "descricao": "...", "project_id": 3, "project_name": "...",
   "versao": 3, "versao_id": 41, "atualizado_em": "2026-11-26 08:00:00", "definicao": { ... }}
]}
```

O app atual continua usando `GET api/questionnaires`, que **não** traz os estruturados.

### `POST api/estruturado/enviar`

Envia a entrevista inteira de uma vez:

```json
{
  "client_id": "uuid-da-entrevista", "questionnaire_id": 12, "versao": 3,
  "started_at": "2026-11-26T09:10:00-03:00", "completed_at": "...", "edited_at": null,
  "latitude": -11.18, "longitude": -40.51, "location_name": "...",
  "photo_path": "nome_do_arquivo.jpg", "photo_path_2": null, "audio_path": "...m4a", "consent_given": true,
  "respostas": [{"codigo": "q4", "valor": "itapicuru"}, {"codigo": "q53", "nsnr": true},
                {"codigo": "q133", "valor": "sim", "complementos": {"sim": "2"}}],
  "moradores": [
    {"client_id": "uuid-do-morador", "ordem": 1, "nome": "Ana Maria", "status": "concluido",
     "respostas": [{"codigo": "q18", "valor": "mulher"}]}
  ],
  "calculados": {"domicilio": {"q109": {"valor": 4100, "incompleto": false}},
                 "moradores": {"uuid-do-morador": {"idade": {"valor": 46, "incompleto": false}}}}
}
```

**Resposta:**

```json
{"success": true, "id": 812, "duplicated": false, "edited": false,
 "partes": {"respostas": 154, "moradores": 3, "calculados": 6},
 "missing_media": ["fachada_812.jpg"], "revisao": null, "erros": [], "alertas": [], "message": "Entrevista gravada."}
```

**Regras do envio:**
- **Duplicata:** o mesmo `client_id` não duplica. Com `edited_at` mais novo, a entrevista é **substituída por inteiro**, com os moradores.
- **Prova de gravação:** só o `id` da resposta prova que a entrevista foi gravada. O app só a libera depois de receber o `id` **e** conferir que `partes.moradores` é igual ao que enviou.
- **Mídia faltando:** se `missing_media` vier com nomes, o app reenvia as fotos e o áudio (pelos endpoints de hoje) e depois reenvia a entrevista.
- **Inconsistência:** obrigatória vazia, regra quebrada ou cálculo diferente **não recusam** o envio. A entrevista é gravada com `revisao: "pendente"` e aparece na fila de revisão do painel. O cálculo que vale é o do servidor.
- **Erros que recusam:**
  - `400` com `json_error`: corpo ilegível ou sem `client_id`/`questionnaire_id`;
  - `404`: formulário inexistente;
  - `413`: corpo grande demais;
  - `422`: formulário sem versão publicada.

## 6. Banco

- `questionnaires.formato`: `simples` (padrão) ou `estruturado`. Também ganhou `estrutura_fonte` (o rascunho) e `estrutura_versao` (a versão publicada).
- `estrutura_versoes`: definição e texto de cada versão publicada.
- `form_responses`: o cabeçalho da entrevista, com as colunas novas `estrutura_versao_id`, `calculados`, `alertas`, `revisao_status` e `revisao_erros`.
- `moradores`: uma linha por morador, com o `client_id` do aparelho.
- `respostas_estruturadas`: uma linha por questão respondida, do domicílio ou de um morador (`morador_id`). Os campos calculados ficam com `calculado = true` e `incompleto`.

## 7. Biblioteca: conjuntos, temas, entrevistados e repetições (frente L)

A biblioteca fica no painel, em Formulários estruturados → **Conjuntos e
temas** e **Entrevistados**.

### Conjuntos de perguntas

Um conjunto é um texto com **uma única etapa** (`@domicilio`, `@morador` ou
`@repetir`), publicado em versões.

**Para usar num formulário:**

```
@usar composicao_domicilio            [título opcional]
  se: q12 == "Sim"                    abre por resposta
  tema: Reassentamento                abre pelo tema escolhido
  categoria: Chefe de família         abre pela categoria do entrevistado
  entrevistado: E001, E002            abre para estes entrevistados
```

**Versões:**
- Ao **publicar o formulário**, o compilador pega a última versão publicada de cada conjunto e grava `conjunto: {id, codigo, versao, nome}` na etapa.
- Uma versão nova do conjunto só chega ao formulário quando ele é publicado de novo.
- As entrevistas já feitas ficam na versão em que foram coletadas.

Esses mesmos atributos de etapa (`se`, `tema`, `categoria` e `entrevistado`)
valem em qualquer `@domicilio`, `@morador` ou `@repetir`, escritos antes da
primeira questão. Juntos, eles viram a **condição da etapa** (`"se"` no
JSON): quando ela é falsa, todas as questões da etapa ficam ocultas.

### Temas

`# temas: Reassentamento, Saúde` no cabeçalho cria a questão `temas`
(múltipla escolha, obrigatória) no início do formulário. O entrevistador
marca os temas daquela entrevista, e as etapas com `tema: X` só abrem se X
for marcado. O catálogo de temas do painel registra quais conjuntos
pertencem a cada tema.

### Entrevistados pré-cadastrados

- **Cadastro:** por projeto, individual ou por planilha (CSV ou XLSX).
  - Colunas: `codigo`, `nome`, `categoria`, `localidade`, `latitude` e `longitude`.
  - As outras colunas viram campos extras.
- **No formulário:**
  - `# entrevistado: opcional` (ou `obrigatorio`) faz o app oferecer a escolha do entrevistado no início.
  - Nas expressões, os dados aparecem como `ent_codigo`, `ent_nome`, `ent_categoria` (em código: "Chefe de família" vira `chefe_de_familia`), `ent_localidade` e `ent_<campo extra>` (ex.: `ent_telefone`).
  - `preencher: ent_nome` numa questão do domicílio sugere a resposta a partir do cadastro.
- **No envio:** vai o `entrevistado_id`, e o servidor confere com o cadastro.

### Repetições (`@repetir`)

```
@repetir bens Benfeitorias e bens
  por: q30          quantidade informada na questão 30 (inteiro do domicílio); opcional
  max: 20           e/ou min: 1
  rotulo: Bem {n}
31. Tipo de bem [texto]
32. Valor [moeda nsnr]
```

- Fora do grupo, use `soma_grupo("bens", q32)`, `contar_grupo("bens", filtro)`, `existe_grupo(...)` e `todos_grupo(...)`.
- Dentro do grupo, as questões enxergam o domicílio.
- Com `por:`, a quantidade preenchida precisa bater com a informada; quando não bate, a entrevista não finaliza.

### JSON e API

- **Definição:**
  - `"temas": [...]` e `"entrevistado": {"modo": "opcional|obrigatorio"}` no topo;
  - etapas com `"se"`, `"conjunto"`, e `"nivel": "grupo"` com `"grupo"` e `"repetir": {"por", "max", "min", "rotulo"}`;
  - questões com `"preencher"`.
- **Entrevistados:** `GET api/estruturado/entrevistados?project_id=&desde=AAAA-MM-DD HH:MM:SS` devolve `data: [{id, project_id, codigo, nome, categoria, localidade, latitude, longitude, campos, contexto, ativo, atualizado_em}]` e `servidor_em`.
  - **Sincronização incremental:** sem `desde`, vêm só os ativos. Com `desde`, vêm os alterados depois dessa data, inclusive os removidos (`ativo: false`).
  - **`contexto`:** já traz os valores como a linguagem enxerga (`ent_*`).
- **Envio:** o corpo aceita também
  `"entrevistado_id": 15, "grupos": {"bens": [{"client_id": "uuid", "indice": 1, "respostas": [...]}]}`.
  A resposta ganha `partes.repeticoes`.
- **Banco:** `conjuntos`, `conjunto_versoes`, `temas`, `tema_conjuntos`, `entrevistados`, `repeticoes`, `form_responses.entrevistado_id` e `respostas_estruturadas.repeticao_id` (`sql/conjuntos_migration.sql`).
