# Levar à produção os ajustes da evolução (outubro/2026)

Para: o agente de código do projeto de produção (`painel.sxdata.com.br`).

Os ajustes foram desenvolvidos e testados no ambiente de evolução
(`sxdata.online`, uma VPS separada) em duas rodadas:

- **Rodada 1** (13 a 15/10): **já está na produção**, conferido em 19/10. A
  seção 1 diz o que confirmar; não refaça.
- **Rodada 2** (19/10): **é o trabalho a fazer.** São as seções 2 a 6, mais o
  procedimento de atualização (seção 7) e a publicação do Web App e do
  Android pela Play Store (seção 8).

| Seção | Problema | Gravidade | Onde |
|---|---|---|---|
| 2 | Editor de questionário pode **apagar perguntas e as respostas delas** | Perda de dados | Painel + configuração do PHP |
| 3 | **Atualizar o app pode perder coletas pendentes** (armazenamento e assinatura do APK) | Perda de dados | App + procedimento |
| 4 | Foto de evidência registrada que não chega ao painel | Perda de evidência | Painel + app |
| 5 | "Requer foto" não obrigava a foto: dava para finalizar sem | Coleta sem evidência | App |
| 6 | "Próxima" não avança em pergunta que aparece por lógica condicional | Coleta travada | App |

Para o usuário, o mais importante é que **nenhuma entrevista nem foto se
perca**: nem na coleta, nem na sincronização, nem ao **atualizar o app nos
aparelhos**.

**Regras:**

- Faça commit ao terminar cada seção. **Não faça push, deploy nem altere o
  banco sem a confirmação do usuário.** O painel publica pelo `git pull` do
  cPanel, a partir do `main`.
- **Publique o painel antes do app.** As mudanças do painel são compatíveis
  com o app atual: o campo novo da resposta é opcional e o contrato do upload
  continua o mesmo. O app novo aproveita o que o painel novo devolve.
- Nenhuma mudança precisa de migração de banco.

---

## 0. Onde está cada coisa

| | Evolução (origem) | Produção (destino) |
|---|---|---|
| Painel (CodeIgniter 3 + PostgreSQL) | `github.com/leoharley/sxdata-painel-dev` | `github.com/leoharley/sxdata` |
| App (Flutter, Android + web) | `github.com/leoharley/sxdata-app-dev` | `github.com/leoharley/sxdata-app` |
| Cópia local nesta máquina | `C:\Demanda_Jose_Neto\Sistema\Dev\Evolução-Out-2026\sxdata-painel-dev` e `...\sxdata-app-dev` | — |
| URL | `painel.sxdata.online` e `app.sxdata.online` | `painel.sxdata.com.br` e `painel.sxdata.com.br/app/` |

### Commits da rodada 2

| Repositório de evolução | Commit | Seção |
|---|---|---|
| `sxdata-painel-dev` | `2cef8b8` | 2: trava do editor contra formulário cortado |
| `sxdata-painel-dev` | `01c3f92` | 4: upload de foto, `missing_media` e aviso no painel |
| `sxdata-app-dev` | `c1d9e74` | 6: "Próxima" com lógica condicional, com teste |
| `sxdata-app-dev` | `f833853` | 4: correções de foto no app, com testes |
| `sxdata-app-dev` | `2bd6ccc` | 5: foto obrigatória com "Requer foto", com teste |
| `sxdata-app-dev` | `dae514b` | 3: armazenamento que não perde coletas, com testes |
| `sxdata-app-dev` | `8e159e8` | 3: `applicationId` do app da Play Store (`br.com.sxdata.app`) |

No app, faça o cherry-pick **nesta ordem**, que é a ordem em que foram feitos:
`c1d9e74`, `f833853`, `2bd6ccc`, `dae514b`, `8e159e8`.

### Como trazer

```bash
git remote add evolucao https://github.com/leoharley/sxdata-app-dev.git   # no app
git fetch evolucao
git cherry-pick <hash>
```

- No painel, use o mesmo comando com `sxdata-painel-dev.git`.
- Também dá para buscar da cópia local, com o caminho da pasta no lugar da
  URL.

A produção tem commits que a evolução não tem. Se der conflito, aplique à mão
a **intenção** descrita em cada seção, lendo o diff original com
`git show <hash>`. Não copie arquivos inteiros da evolução por cima.

Atenção a um conflito provável: a produção removeu a estratificação de cotas
do app (`21a2f94`), o que mexe em `lib/screens/question_screen.dart`, o
mesmo arquivo do `c1d9e74` e do `f833853`.

### O que NÃO trazer

São da VPS de evolução (Oracle/Nginx):

- `62b4050`, `2a6149c`, `f9eccad`, `81a22b9`;
- qualquer coisa em `deploy/evolucao/` ou com `sxdata.online`.

---

## 1. O que já está na produção (conferir, não refazer)

### Base de sincronização e sessão

Estes dois commits estavam no `main` de produção antes da evolução começar:

- **App `a57ac3f`:**
  - fila de sincronização com confirmação pelo id do servidor;
  - `client_id` para o servidor reconhecer reenvios;
  - tela de enviados;
  - aviso de sessão expirada sem logout;
  - renovação do token em `auth/verify`;
  - mídias da versão web guardadas no navegador até sincronizar.
- **Painel `cb52020`:**
  - token assinado de 28 dias;
  - `forms/submit` idempotente;
  - `api/audios/upload`;
  - colunas `client_id`, `edited_at` e `audio_path`, que já estão no banco
    de produção.

### Rodada 1 (aplicada pela produção em 15 e 18/10)

| Ajuste | Commit na evolução | Commit na produção |
|---|---|---|
| Upload de áudio/foto dava 500 com token válido | `428f369` | `sxdata@9b5774d` |
| Ouvir o áudio na listagem de respostas | `fc16c0d` | `sxdata@03440a1` |
| Endereço da coluna "Local" sob demanda | `50fa6c2` | `sxdata@b342582` |
| Tipo de pergunta Telefone (painel) | `b74a8c1` | `sxdata@ba32e21` |
| URL da API definida no build | `429d89b` | `sxdata-app@3645059` |
| Pergunta Telefone com máscara (app) | `083e7de` | `sxdata-app@a4f2692` |
| Versão 3.3.0 | `6a3fb00` | `sxdata-app@36550fe` (hoje 3.3.1+22) |
| Web App em `/app/` | — | `sxdata@35bb5c4`, `e5b211a`; `sxdata-app@55d91b0` |

Conferido de fora em 19/10:

- `https://painel.sxdata.com.br/app/version.json` responde **3.3.1 (build
  22)**;
- `POST /api/photos/upload` sem token responde 401.

A produção também fez commits próprios:

- remoção da estratificação de cotas (`sxdata@d56e34c`,
  `sxdata-app@21a2f94`);
- correção da pasta `application/cache/` (`sxdata@5b92ff1`);
- assinatura do release pela keystore de upload da Play Store
  (`sxdata-app@c15e4a5`).

**Confira:**

1. Esses commits estão no `main` dos dois repositórios
   (`git log --oneline`).
2. O painel **publicado** é o `main`. No cPanel, *Git Version Control* mostra
   o HEAD.

Se algo da rodada 1 faltar, os detalhes estão no **Apêndice A**.

---

## 2. Editor de questionário pode apagar perguntas e respostas (prioridade 1)

### O problema

O PHP descarta **sem erro** todo campo de formulário além de
`max_input_vars`, que por padrão é 1000. O editor envia vários campos por
pergunta e por opção. O questionário "Diagnóstico de Viabilidade Técnica"
(157 perguntas e 261 opções) passa muito disso.

Em `Questionnaires::edit()`, com o **modo de edição de perguntas** ligado
(`edit_mode = 1`), toda pergunta que não veio no formulário é tratada como
excluída:

```php
$questions_to_delete = array_diff($existing_question_ids, $processed_question_ids);
foreach ($questions_to_delete as $question_id_to_delete) {
    $this->Question_model->delete($question_id_to_delete);
}
```

Com o formulário cortado, as perguntas do fim são apagadas. Como
`question_responses` tem `ON DELETE CASCADE`, **as respostas dessas perguntas
em todas as entrevistas vão junto**.

Isso aconteceu na evolução em 19/10, às 08:34: um salvamento do questionário
18 gerou `PHP Warning: Input variables exceeded 1000`. Foi sem o modo de
edição de perguntas, então nada se perdeu.

### O que fazer

1. **Antes de tudo, verifique se já aconteceu na produção.**
   - Procure `Input variables exceeded` no log de erros do PHP no cPanel
     (`error_log` na pasta do site ou em *Metrics → Errors*).
   - Para cada ocorrência, veja no log de acesso qual `POST
     /questionnaires/edit/<id>` foi feito naquele horário.
   - Compare as perguntas daquele questionário com um backup anterior. O
     usuário tem dumps do banco.
   - **Relate ao usuário antes de qualquer correção de dados.**
2. **Aumente o limite no cPanel:** *MultiPHP INI Editor* →
   `max_input_vars = 10000` para o domínio do painel. Confirme o valor
   efetivo com um `phpinfo()` temporário e apague-o depois. Se não tiver
   acesso, peça ao usuário.
3. **Traga o commit `2cef8b8`** (`application/controllers/Questionnaires.php`):
   - método `post_truncado()`: conta os campos de `$_POST`
     (`array_walk_recursive`) e devolve `true` se o total atingiu
     `max_input_vars`;
   - em `edit()` e `create()`, antes de processar o `POST`: se veio cortado,
     **não salva nada**, grava a mensagem de `mensagem_post_truncado()` em
     `flashdata('error')` e redireciona de volta ao editor.

### Teste

Com uma sessão de administrador, envie para `/questionnaires/edit/<id de um
questionário de TESTE>` um `POST` com mais campos que o limite, por exemplo
1.200 campos `x[]`.

- O resultado esperado é um redirecionamento de volta ao editor, com o
  questionário **idêntico**: compare `md5(row(q.*)::text)` antes e depois.
- **Faça isso com o limite ainda em 1000, ou não vai disparar.** Com 10.000,
  o salvamento passaria de verdade.
- Use um questionário de teste: se a trava falhar, ele é salvo.

---

## 3. Atualizar o app não pode perder coletas pendentes

Há dois riscos diferentes: um no **código** (commit `dae514b`) e um na
**instalação do APK** (procedimento da seção 7).

### 3.1 Armazenamento (commit `dae514b`)

As coletas ficam numa lista JSON em `SharedPreferences` (chave
`form_responses`; na web, o `localStorage`), regravada **inteira** a cada
alteração. Em todo o código **versionado** os formatos são compatíveis: a
versão nova lê exatamente o que o código de nov/2025 (1.0.0+3) e a 3.3.x
gravam, com as mesmas chaves (`form_responses`, `offline_photos_metadata`,
IndexedDB `sxdata_media`).

**Mas o app da Play Store não saiu de código versionado.** O identificador
dele, `br.com.sxdata.app`, não aparece em nenhum commit de nenhum dos
repositórios, nem no backup de 12/10. Ele foi gerado com alterações locais
ou por outra pessoa. Então a compatibilidade com o que está **instalado nos
celulares** só se confirma no teste da faixa de teste (seção 3.2), que é
**obrigatório** antes de liberar aos aplicadores.

O defeito estava em como a lista era regravada:

1. **Registro ilegível era apagado.** `getFormResponses()` pula o registro
   que não consegue ler, e qualquer gravação seguinte regravava a lista sem
   ele.
2. **`saveFormResponse` recomeçava com a lista vazia** se um único registro
   fosse ilegível: salvar a próxima entrevista apagava **todas** as
   pendentes.
3. **`saveFormResponse` e `updateFormResponse` não passavam pela fila de
   gravações (`_withFormsLock`).** Salvar uma coleta enquanto a
   sincronização atualizava status podia perdê-la. O teste novo reproduz
   isso.

O que mudou em `lib/services/local_storage_service.dart`:

- novo `_persistForms(prefs, forms)`, usado por **todas** as gravações da
  lista:
  - mantém, como estão, os registros que esta versão não consegue ler;
  - se o texto inteiro não for JSON válido, copia-o para
    `form_responses_corrompido_<ms>` antes de sobrescrever;
- `saveFormResponse` usa `getFormResponses()`, que é tolerante registro a
  registro, nunca mais "lista vazia";
- `saveFormResponse` e `updateFormResponse` passam por `_withFormsLock`.
  **Não chame** um deles de dentro de outra operação da fila, porque
  travaria;
- `resetWriteQueueForTesting()` (`@visibleForTesting`), para testes de
  widget. Num `testWidgets`, cada teste tem a sua zona de relógio simulado,
  e um `Future` agenda seus `.then` na zona onde nasceu; sem o reset, a
  fila criada num teste trava o seguinte. No app há uma zona só.

Leitura tolerante:

- `FormResponse.fromJson` e `QuestionResponse.fromJson` aceitam números
  gravados como texto, inteiro ou double (no navegador não há distinção);
- sem `started_at`, vale `completed_at`;
- só lança erro se faltar questionário, aplicador ou data.

Teste novo `test/update_keeps_pending_test.dart`:

- lê o formato **exato** gravado pela 1.0.0+3;
- cobre os três defeitos e a lista corrompida;
- sem a correção, 4 dos 5 falham. O que passa é "formato da 1.0.0+3 é
  legível", o que confirma a compatibilidade.

### 3.2 Android: Play Store, identificador e assinatura

O Android só instala uma versão **por cima** da instalada, mantendo os
dados, se o **pacote** e o **certificado de assinatura** forem os mesmos.

- O pacote: o app da loja é `br.com.sxdata.app`; o código gerava `com.example.sxdata` (veja abaixo).
- O certificado **não** é garantido:

| APK | Certificado (SHA-256) |
|---|---|
| `apk/homologacao/app-release_sxdata_10-07-25.apk` (repositório do painel) | `9C:EA:7C:BE:FC:36:F2:7D:…:0E:DF:52:E6`, chave de **debug de outro computador** |
| Chave de debug desta máquina (`~/.android/debug.keystore`) | `94:24:5F:95:7F:5B:40:80:…:1A:EA:B7:BC` |
| Keystore de upload da Play Store (`sxdata-app@c15e4a5`) | uma terceira chave |

Se o APK novo tiver outro certificado, o celular recusa a atualização ("App
não instalado"). O caminho natural é desinstalar o antigo, e **desinstalar
apaga todas as coletas e fotos pendentes**. O código não resolve isso: um
app com outra assinatura não acessa os dados do anterior.

#### O app dos aplicadores vem da Play Store (confirmado pelo usuário)

Com o Play App Signing, o Google assina o app com a **chave de assinatura
dele**. Toda versão publicada pela loja chega aos celulares como atualização
**por cima**, com os dados preservados, **desde que**:

1. o **identificador do pacote** (`applicationId`) seja **exatamente** o do
   app na Play Console;
2. o número de build (`versionCode`) seja maior que o publicado. A loja tem
   a 1.0.0+3; `3.3.2+23` serve;
3. o pacote (AAB) seja assinado com a **chave de upload** registrada na Play
   Console. É o que o `sxdata-app@c15e4a5` configurou, via
   `android/key.properties`.

**Atenção ao identificador.** O app da loja é **`br.com.sxdata.app`**
(<https://play.google.com/store/apps/details?id=br.com.sxdata.app>, "SXData",
de LS GLOBAL TECNOLOGIA). O código de produção ainda gera
`com.example.sxdata`, que a Play Store nem aceita.

- **Traga o commit `8e159e8`** do `sxdata-app-dev`. Ele muda só o
  `applicationId` em `android/app/build.gradle.kts` para `br.com.sxdata.app`;
  o `namespace` e o pacote Kotlin continuam `com.example.sxdata`.
- Confira na Play Console o `versionCode` que está publicado hoje. O build
  novo precisa ser maior.
- Gerar com qualquer outro identificador cria um **segundo app** no celular,
  que não enxerga as coletas pendentes do app da loja.

**Nunca distribua um APK por fora da loja** (link, WhatsApp, arquivo) para
quem instalou pela Play Store. A assinatura não bate com a do Google, o
celular recusa, e a tentação é desinstalar o app, levando os pendentes.

**A chave de upload.** O `sxdata-app@c15e4a5` configura uma keystore de
upload via `android/key.properties`. Como o app da loja foi publicado a
partir de código fora dos repositórios, confirme na Play Console (*Teste e
lançamento → Configuração → Integridade do app → Assinatura do app*) que o
certificado de upload registrado é o dessa keystore. Se não for:

- a Play Console recusa o envio. Os dados dos aplicadores não correm risco,
  porque a chave que assina o app nos celulares fica com o Google;
- descubra com o usuário quem publicou ("LS GLOBAL TECNOLOGIA") e onde está
  a keystore de upload;
- se ela se perdeu, o proprietário da conta pode pedir a **redefinição da
  chave de upload** na própria Play Console. Não gere um app novo com outro
  identificador.

**Teste obrigatório na faixa de teste** (interno ou fechado), num celular
que tenha a versão **atual da loja** com **coletas e fotos pendentes**:

1. no app atual da loja, finalize ao menos uma coleta com foto **sem
   sincronizar**;
2. coloque esse celular na faixa de teste e atualize pela Play Store;
3. abra o app, faça login (a troca de versão pede uma vez) e confira que as
   pendentes e as fotos continuam lá;
4. sincronize e confira no painel.

Se as pendentes **não** aparecerem depois de atualizar, **pare**. Não promova
a versão e relate ao usuário: o app da loja grava num formato ou numa chave
diferentes do código versionado. Os registros ficam guardados (a versão nova
não apaga o que não lê), mas não sobem até isso ser tratado.

Só depois de passar promova para a faixa que os aplicadores usam.

**Se algum celular tiver o app instalado por fora da loja** (por exemplo, o
`apk/homologacao/app-release_sxdata_10-07-25.apk`, assinado com chave de
debug), a atualização pela loja não serve para ele: use o procedimento da
seção 7. Para conferir a origem e a assinatura de um aparelho ligado por
cabo:

```bash
adb shell dumpsys package br.com.sxdata.app | grep -iE "installerPackageName|signatures"
```

O instalador `com.android.vending` significa Play Store.

O Web App (PWA) não tem esse problema: a atualização é pelo service worker,
na mesma origem, e os dados ficam.

---

## 4. Foto de evidência registrada que não chega ao painel

### Caminhos de perda encontrados

**App Android:**

- **A1.** Foto que falhava uma vez no envio (rede, erro 500) virava `error`
  e **nunca mais era reenviada**: `PhotoStorageService.getPendingPhotos()` só
  pegava `pending`. Como a trava antes do envio contava `error` como
  pendente, ela **segurava todas as coletas do aparelho para sempre**.
- **A2.** Se a cópia da foto para a fila (`savePhotoOffline`) falhasse, o
  `submitFormWithPhoto` gravava o **caminho do aparelho** (`/data/...jpg`) em
  `photo_path`. A foto nunca entrava na fila, e o painel recebia um nome que
  não existe.

**App web:**

- **W1.** "Refazer foto" apagava a foto da fila (IndexedDB), mas o
  formulário continuava citando-a. Finalizando sem tirar outra, a coleta
  subia citando uma foto que nunca chegaria.
- **W2.** Foto com zero bytes (falha na redução) era descartada como órfã na
  sincronização, mas a coleta continuava citando-a.

**Ambos:**

- **G1.** `setPhoto` sem formulário ativo descartava a foto em silêncio.
- **G2.** O app ignorava o nome devolvido pelo servidor.

**Servidor:**

- **S1.** `api/photos/upload` usava a biblioteca de upload do CodeIgniter.
  Ela renomeia o arquivo quando o nome já existe (`foto.jpg` → `foto1.jpg`),
  enquanto a coleta continuava citando `foto.jpg`. Ela também valida pelo
  MIME que o aparelho informa.
- **S2.** Nada avisava o app de que a coleta foi gravada citando uma foto que
  o servidor não tem. O painel mostrava uma imagem quebrada, sem destaque.

### Painel (commit `01c3f92`)

- **`application/controllers/api/Photos.php`**, reescrito no padrão do
  `api/Audios.php`:
  - valida o token com `Api_token`;
  - limite de 15 MB e extensões `jpg`, `jpeg`, `png` e `webp`;
  - valida o **conteúdo** com `getimagesize()`;
  - **mantém o nome enviado pelo app**, apenas higienizado;
  - se o arquivo já existe com o **mesmo conteúdo** (`md5_file`), responde
    sucesso sem criar cópia, porque é um reenvio;
  - se já existe com **outro conteúdo**, grava com um sufixo aleatório e
    devolve o nome novo em `filename`;
  - a resposta continua `{success, filename, file_size, file_type}`.
- **`application/controllers/api/Forms.php`:**
  - `respond_success()` inclui `missing_media`: lista de `photo_path`,
    `photo_path_2` e `audio_path` do registro gravado cujo arquivo não existe
    em `uploads/photos` ou `uploads/audios`. Um nome com `/` sempre conta
    como faltando;
  - novo `repair_media($existing, $json)`, chamado no reenvio de coleta já
    gravada (o caminho "Form already synced"): se o registro cita mídia
    ausente e o app agora cita um arquivo que **existe**, o registro passa a
    citá-lo. Sem isso, o app reenviaria a foto para sempre.
- **`application/controllers/Responses.php`**, mais as views
  `admin/responses/index.php` e `view.php`:
  - novo `foto_no_servidor()`;
  - foto citada mas ausente aparece como **"Foto registrada, mas o arquivo
    não chegou ao servidor"** na listagem (botão desabilitado) e no detalhe
    (aviso com o nome esperado), em vez de imagem quebrada.

### App (commit `f833853`)

- **`lib/services/photo_storage_service.dart`:**
  - `getPendingPhotos()` inclui `error` com arquivo existente, que é
    reenviada. Arquivo que sumiu vira `lost` e não prende nada;
  - novos `pendingFilenames()` e `requeue(filename)`, que voltam para
    `pending` uma foto `synced` cujo arquivo ainda existe;
  - no upload, se o servidor devolver outro `filename`, chama
    `LocalStorageService.replaceMediaReference()` e atualiza os metadados.
- **`lib/services/local_storage_service.dart`:** novo
  `replaceMediaReference(oldName, newName)`, sob a trava de escrita
  `_withFormsLock`.
- **`lib/providers/form_provider.dart`:**
  - `submitFormWithPhoto`: se a cópia da foto falhar, **retorna `false`** com
    o motivo em `submitError`. Nunca grava o caminho do aparelho.
    **Atenção:** não use exceção aqui, porque o `catch` faz um "salvamento de
    emergência" com o `photoPath` atual;
  - `syncPendingForms`:
    - no início, coleta antiga com caminho do aparelho em `photo_path` tem a
      foto posta na fila (`_requeueDevicePhotoPaths`);
    - depois de enviar as mídias, **relê** as coletas, porque o nome pode ter
      mudado;
    - a trava global foi substituída por uma trava **por coleta**
      (`pendingMediaOfForm`): só fica retida a coleta cuja foto ou áudio
      ainda está na fila. Na web, as partes do áudio
      (`audio_web_<sessão>_p<n>`) também contam;
    - se a resposta trouxer `missing_media` e o aparelho ainda tiver a foto,
      ela volta para a fila e a coleta **continua pendente**. Na próxima
      sincronização a foto sobe, e o reenvio, deduplicado por `client_id`,
      confirma;
  - `setPhoto`/`setPhoto2` retornam `bool`;
  - novos `clearPhoto`/`clearPhoto2`;
  - novo `submitError`;
  - `syncWebPendingMedia` respeita o `filename` devolvido.
- **`lib/models/form_response.dart`:** `copyWith(clearPhotoPath:,
  clearPhotoPath2:)`, porque `null` significa "manter".
- **`lib/screens/photo_capture_screen.dart`:**
  - foto vazia é recusada;
  - se `setPhoto` falhar, o app avisa "Não foi possível anexar a foto";
  - "Refazer foto" chama `clearPhoto`;
  - a finalização mostra `submitError`.
- **Teste novo `test/photo_sync_test.dart`:** 5 cenários de ponta a ponta
  contra um servidor simulado (`MockClient` e `http.runWithClient`, com o
  `path_provider` apontando para uma pasta temporária), mais testes das
  peças. Os 5 cenários **falham** sem a correção.

### Antes de publicar, levante o estado na produção (só leitura)

```sql
-- Coletas que citam foto: confira se cada arquivo existe em uploads/photos
SELECT id, questionnaire_id, applied_by, created_at, photo_path, photo_path_2
FROM form_responses
WHERE coalesce(photo_path,'') <> '' OR coalesce(photo_path_2,'') <> ''
ORDER BY id;

-- Questionários que exigem foto e coletas sem foto
SELECT r.id, r.questionnaire_id, r.applied_by, r.created_at
FROM form_responses r JOIN questionnaires q ON q.id = r.questionnaire_id
WHERE q.requires_photo AND coalesce(r.photo_path,'') = ''
ORDER BY r.id;
```

Para cada `photo_path`, confira se o arquivo existe na pasta do servidor.
Relate ao usuário:

- quantas coletas citam foto inexistente;
- quantas citam um caminho do aparelho (contém `/`);
- quantas exigiam foto e vieram sem.

Na evolução, as respostas 146 e 147 do questionário 17 vieram sem foto. Pelo
log, foram testes rápidos: 5 e 12 segundos depois do login, uma delas pelo
navegador do computador. Elas passaram porque a checagem "foto obrigatória"
estava comentada; a seção 5 corrige isso.

### Testes do servidor (feitos na evolução; repita na produção com nomes `zz_`)

| # | Ação | Esperado |
|---|---|---|
| 1 | Upload de JPEG novo | 200, `filename` igual ao enviado |
| 2 | Mesmo arquivo de novo | 200, mesmo nome, **uma** cópia no servidor |
| 3 | Outro JPEG com o mesmo nome | 200, `filename` com sufixo; o original intacto |
| 4 | Arquivo que não é imagem, com `.jpg` | 400 |
| 5 | `forms/submit` citando foto inexistente | 200 com `missing_media: ["…"]` |
| 6 | Reenvio do mesmo `client_id` citando foto existente | `duplicated: true`, `missing_media: []`, `photo_path` atualizado |

Para gerar um token de teste e JPEGs válidos, use PHP no servidor: GD para os
JPEGs e o HMAC do `Api_token` com `API_TOKEN_SECRET`. **Apague o registro de
teste e os arquivos `zz_*` no final.** O registro de teste consome um id da
sequência; isso é esperado.

---

## 5. "Requer foto" obrigatório: não finaliza sem foto

**Commit `2bd6ccc`** (app). Arquivos: `lib/screens/photo_capture_screen.dart`
e `test/required_photo_test.dart`.

Pedido do usuário: com "Requer foto" marcado no questionário, **não deixar
finalizar sem registrar a foto**. A checagem existia em
`PhotoCaptureScreen._completeForm`, mas estava comentada. O botão ficava
cinza e continuava finalizando.

- Novo `PhotoCaptureScreen.fotoObrigatoriaFaltando({requiresPhoto,
  isEditMode, photoPath})` (`@visibleForTesting`).
- A referência é a foto **do formulário**
  (`formProvider.currentForm?.photoPath`), que vale no Android e na web,
  não o estado da tela.
- Em `_completeForm`, se faltar a foto, mostra "Este questionário exige
  foto. Tire a foto para finalizar." e não finaliza.
- O botão "Finalizar" fica cinza pela mesma regra, mas **continua tocável**
  para explicar o motivo.
- O texto da tela passa a ser "Foto obrigatória: tire a foto para
  finalizar".
- **Na edição não se exige foto nova:** o entrevistado já não está presente,
  e vale a foto da entrevista original.
- Só a primeira foto é obrigatória; a segunda continua opcional.

**Teste:** monta a tela real. Sem a correção, o caso "Requer foto sem foto"
finaliza e o teste falha. Os casos "com foto" e "foto opcional" continuam
finalizando.

---

## 6. "Próxima" não avança em pergunta que aparece por lógica condicional

**Commit `c1d9e74`** (app). Arquivos: `lib/screens/question_screen.dart` e
`test/conditional_question_advance_test.dart`.

**Caso:** questionário 18. P4 (id 460, `radio` obrigatória) = "Não" mostra a
P5 (461, `text` opcional). Na P5, "Próxima" não fazia nada.

**Causa:**

- O `PageView` recebia `_BlockForwardSwipePhysics(canAdvance: () =>
  _canAdvanceFromCurrentQuestion(visibleQuestions, formProvider))`, uma
  função que fechava sobre a lista de visíveis **daquele** `build`.
- O `Scrollable` só troca a física quando o **tipo** muda
  (`scrollable.dart`, `_shouldUpdatePosition`). Então valia para sempre a
  física do **primeiro** `build`, quando a P5 estava oculta.
- Com a P5 visível, as posições andam uma casa. Na P5, a física avaliava a
  P6 (462, obrigatória, sem resposta) e travava a animação do `nextPage()` a
  cada quadro, sem mensagem.

**Correção:**

- campo `_visibleQuestions`, atualizado a cada `build`;
- `_canAdvanceFromCurrentQuestion(formProvider)` lê esse campo, nunca uma
  lista capturada;
- **não recalcule as visíveis dentro da física**: ela roda a cada quadro, e
  `isQuestionVisible` imprime uma linha de log por pergunta.

**Não é exclusivo da web.** A física entrou no `ffeb3b4` (junho/2026), e o
app da loja (1.0.0+3) funciona porque provavelmente é de antes dela. Não dá
para confirmar pelo histórico, porque ele não saiu de código versionado (seção
3.1). De todo modo, qualquer versão Android gerada a partir do código atual
sem esta correção teria o mesmo defeito.

**Teste:** `test/conditional_question_advance_test.dart` monta a tela real
com as 8 primeiras perguntas do questionário 18 e simula os plugins (gravador
e wakelock). Sem a correção, falha na P5; com ela, chega à P6.

---

## 7. Procedimento de atualização sem perder pendentes

Vale para qualquer troca de versão nos celulares. Passe ao usuário, que
coordena os aplicadores.

**Antes de tudo, no servidor:** confirme que o painel publicado tem a
correção do upload (`sxdata@9b5774d`) e a seção 4. Sem elas, fotos e áudios
não sobem, e "sincronizar antes de atualizar" não esvazia nada.

**Web App (PWA):**

1. Publique a versão nova (seção 8).
2. No celular, o app mostra **"Nova versão disponível"**. Tocar em
   **Atualizar** só depois de **finalizar** a entrevista em andamento:
   respostas ainda não finalizadas ficam só na memória e se perdem ao
   recarregar. As finalizadas e as fotos ficam no navegador e **não** se
   perdem.
3. Depois de atualizar, o app pede login uma vez (troca de versão) e as
   pendentes continuam lá.
4. **Não** limpar dados do navegador nem desinstalar o PWA com coletas
   pendentes. Isso apaga o `localStorage` e o IndexedDB.

**Android pela Play Store (o caso dos aplicadores):**

1. Publique a versão nova na faixa de teste interno (seção 8.1) e faça o
   teste obrigatório da seção 3.2.
2. Promova para a faixa dos aplicadores. Cada celular **atualiza pela loja**,
   por cima. As coletas e fotos pendentes ficam, e o app pede login uma vez.
3. Oriente os aplicadores a **finalizar** a entrevista em andamento antes de
   atualizar, a **não desinstalar** o app e a **não instalar APK** recebido
   por fora.

**Android instalado por fora da loja (exceção):**

1. **Se o APK novo tem a mesma assinatura do instalado** (seção 3.2):
   instalar por cima. Os dados ficam, e as pendentes sobem na próxima
   sincronização.
2. **Se a assinatura é diferente, ou se não der para confirmar:**
   1. No app **antigo**, com internet, tocar em **Sincronizar Tudo** até o
      painel mostrar **0 pendentes**.
   2. **Conferir no painel** as coletas desse aplicador. A versão 1.0.0+3 é
      de antes da fila de sincronização: ela marcava a coleta como enviada
      antes de enviar as fotos. Com a seção 4 no ar, uma foto que não chegou
      aparece como **"Foto registrada, mas o arquivo não chegou ao
      servidor"**. Se houver alguma, **não desinstale**: sincronize de novo e
      confira outra vez.
   3. Só então desinstalar o antigo e instalar o novo.
3. Na dúvida, não desinstale: o aplicador pode usar o Web App enquanto isso.

---

## 8. Publicar as versões novas

A versão sai em dois canais, **com o mesmo número de versão**: o Web App em
`https://painel.sxdata.com.br/app/` e o Android pela Play Store (seção 8.1).

### Web App

A estrutura já existe na produção: `.htaccess` da pasta `app/` com
`DirectoryIndex`, rotas internas, `no-cache` e gzip (`sxdata-app@55d91b0`), e
`/app/` no `.gitignore` do painel (`sxdata@e5b211a`). O trabalho é **gerar e
publicar o build novo**.

**Versão.** Suba para `3.3.2+23`, ou o próximo número depois do que
estiver no `main` **e** do `versionCode` publicado na Play Console, e
confirme com o usuário. Mudar a versão força **um** novo login: só o token é
removido, os pendentes ficam no aparelho.

**Build:**

```bash
flutter build web --release --base-href /app/ --no-web-resources-cdn
dart run tool/patch_service_worker.dart build/web/flutter_service_worker.js
```

- **O patch do service worker é obrigatório em `/app/`.** Sem ele, o service
  worker do Flutter não acha os arquivos no cache fora da raiz, e o app
  **não abre sem internet**.
- Sem `--dart-define=API_BASE_URL`, a URL da API é a da produção
  (`painel.sxdata.com.br/api`).

O SDK do Flutter desta máquina (`D:\Flutter`, 3.32.8) está danificado. Não o
reinstale sem perguntar ao usuário. Os contornos:

- `export FLUTTER_PREBUILT_ENGINE_VERSION=ef0cd000916d64fa0c5d09cc809fa7ad244a5767`:
  o git interno do SDK está corrompido. O valor é o `engineRevision` de
  `D:\Flutter\bin\cache\flutter.version.json`.
- `MSYS_NO_PATHCONV=1` antes do comando, no Git Bash: sem isso, `/app/` vira
  `C:/Program Files/Git/app/`.
- `--no-tree-shake-icons`: o `font-subset.exe` do SDK está corrompido.
- Caminho com acento (`Evolução`) faz o compilador de shaders falhar:
  compile uma cópia num caminho só com ASCII.

**Antes de publicar, confira:**

- `build/web/index.html` tem `<base href="/app/">`;
- `build/web/main.dart.js` contém `painel.sxdata.com.br/api` e **não**
  contém `sxdata.online`;
- `build/web/flutter_service_worker.js` contém `SXDATA_OFFLINE_SUBDIR_FIX`;
- `build/web/version.json` mostra a versão nova;
- o `.htaccess` de `app/` continua lá depois de publicar.

**Onde publicar:**

- O **conteúdo** de `build/web/` vai para
  `/home/sxdata/painel.sxdata.com.br/app/`.
- **Substitua a pasta inteira**, sem misturar com arquivos de outro build.
  Preserve o `.htaccess`.
- Se não tiver acesso ao servidor, deixe o build pronto e passe ao usuário
  as instruções de envio.

### 8.1 Android pela Play Store (`br.com.sxdata.app`)

**Pré-requisitos (seção 3.2):**

- o commit `8e159e8` aplicado, com `applicationId = "br.com.sxdata.app"`;
- `android/key.properties` com a keystore de **upload registrada na Play
  Console**. O arquivo fica fora do git; o modelo é
  `key.properties.example`;
- `versionCode` maior que o publicado.

**Build:**

```bash
flutter build appbundle --release
```

Use os mesmos contornos do SDK desta máquina, exceto o `--base-href`, que é
só da web. Sem `key.properties`, o build sai assinado com a chave de
**debug**, e a Play Console recusa.

**Antes de enviar, confira:**

- o identificador e a versão no pacote gerado:

  ```bash
  java -jar bundletool.jar dump manifest \
    --bundle build/app/outputs/bundle/release/app-release.aab \
    | grep -E 'package=|versionCode|versionName'
  ```

  Deve mostrar `package="br.com.sxdata.app"`;
- o certificado do AAB é o de upload registrado:
  `keytool -printcert -jarfile build/app/outputs/bundle/release/app-release.aab`.

**Envio:**

1. Envie o AAB para a faixa de **teste interno** e faça o teste obrigatório
   da seção 3.2.
2. Só depois promova para a faixa dos aplicadores, com a autorização do
   usuário.
3. **Não** distribua APK por fora da loja.

Se você não tiver acesso à Play Console, deixe o AAB pronto e passe ao
usuário o passo a passo.

---

## 9. Verificação

**Código:**

- `flutter test`: todos passando. Na evolução foram 119.
- `flutter analyze`: nenhum `error` nem `warning`. Os `info` já existiam.

**Teste de atualização, sem perder pendentes (seção 3):**

- **Web App:**
  1. Com a versão **atual** no ar, faça login no Web App e finalize uma
     coleta com foto, **sem sincronizar**.
  2. Publique a versão nova e toque em **Atualizar**.
  3. Faça login de novo. A coleta e a foto devem estar nos pendentes e
     subir ao sincronizar.
- **Android:** o teste obrigatório na faixa de teste interno da Play Store
  (seção 3.2), num celular com a versão atual da loja e pendentes. **Sem ele
  passar, a versão não vai para os aplicadores.**

**No celular, com um aplicador e um questionário de teste:**

1. No questionário 18, P4 = "Não", P5 e "Próxima" levam à P6 (seção 6).
2. Com foto e sem sinal: tirar a foto, finalizar e sincronizar com sinal.
   A foto aparece no painel.
3. Em questionário com "Requer foto": tentar finalizar sem foto mostra a
   mensagem e **não** finaliza. Tirar a foto, "Refazer" e tentar de novo
   também bloqueia até haver uma foto.
4. Em questionário **sem** "Requer foto": tirar uma foto, tocar em
   "Refazer", finalizar sem foto e sincronizar. O painel **não** mostra foto
   quebrada.
5. No painel, a listagem e o detalhe de uma coleta com foto ausente mostram
   "não chegou ao servidor".

**Regressão de sincronização e sessão.** Isso é o mais importante para o
usuário e precisa continuar funcionando:

6. **Coleta offline:** em modo avião, responder uma entrevista com áudio e
   foto, fechar e reabrir o app. A entrevista continua nos pendentes.
7. **Reenvio:** voltar a conexão e tocar em "Sincronizar Tudo". A entrevista
   sobe, sai dos pendentes, aparece na tela de enviados e, no painel, com
   áudio e foto.
8. **Sem duplicar:** cortar a conexão logo depois de tocar em sincronizar e
   sincronizar de novo. No painel deve existir **um** registro só. Para
   conferir, conte no banco, somente leitura, as linhas de `form_responses`
   com aquele `client_id`.
9. **Edição:** editar a entrevista enviada e sincronizar. O registro é
   **atualizado**, sem criar outro, e a localização original não muda.
10. **Sessão:** reabrir com internet não desloga. Com o token vencido, o app
    avisa "sessão expirada" **sem apagar** os pendentes; ao logar de novo,
    eles sobem.
11. **Offline:** instalado pelo Chrome ("Instalar app"), o app abre em modo
    avião. Isso valida o patch do service worker.

Se algum teste de sincronização falhar, isso tem prioridade sobre o resto.

---

## 10. Ao terminar

Responda ao usuário com:

- **seção 1:** se a rodada 1 está mesmo no `main` e publicada, e o que
  faltava, se faltava algo;
- **seção 2:** se já houve perda por `max_input_vars` na produção, o valor
  efetivo do limite depois do ajuste e o resultado do teste da trava;
- **seção 3:**
  - se o build novo usa `br.com.sxdata.app` (saída do `bundletool`);
  - se o AAB foi assinado com a chave de upload registrada na Play Console,
    e quem tem essa keystore;
  - o `versionCode` que estava publicado e o do build novo;
  - de onde saiu o app que está na loja (quem gerou, a partir de qual
    código), se for possível descobrir;
  - o resultado do teste na faixa de teste da Play Store (pendentes
    preservados depois de atualizar);
  - se há celulares com o app instalado por fora da loja;
  - o resultado do teste de atualização do PWA;
- **seção 4:** o levantamento das coletas com foto ausente, quais commits
  foram trazidos por cherry-pick e quais foram portados à mão (com os
  conflitos), e os testes do servidor;
- **seção 9:** o resultado de cada teste, e quais não conseguiu fazer;
- o que ficou pendente com ele: push, deploy do painel, publicação do Web
  App, versão e distribuição do APK.

---

## Apêndice A. Rodada 1 em detalhe (só se faltar algo na produção)

**Upload de áudio/foto dava 500 com token válido** (`428f369`,
`application/libraries/Api_token.php`):

- `Api_token::is_active_user()` consulta `$this->CI->db`, mas `api/Audios` e
  `api/Photos` não carregam nenhum model, e o `autoload.php` não carrega o
  `database`. Com token válido, o PHP parava com erro fatal (500, corpo
  vazio).
- Correção: antes da consulta,
  `if (!isset($this->CI->db)) { $this->CI->load->database(); }`.

**Ouvir o áudio na listagem** (`fc16c0d`, `Responses.php` `index()` e
`views/admin/responses/index.php`):

- botão de fone em "Ações", com modal e player que toca as partes
  `audio_web_<sessão>_p<n>` em sequência;
- desabilitado se o áudio ainda não chegou;
- depende de `arquivos_de_audio()`, do commit `bdd7c19`.

**Endereço sob demanda** (`50fa6c2`, mesmos arquivos):

- a listagem não geocodifica mais todas as linhas ao abrir; cada linha tem
  o botão "Ver endereço" (`POST responses/get_location`), com cache no
  `localStorage` e timeout de 6 s;
- `get_location()` passou a carregar
  `$this->load->driver('cache', array('adapter' => 'file'))`, que antes
  nunca era carregado.

**Telefone no painel** (`b74a8c1`):

- opção "Telefone" (`phone`) no editor; `'phone'` em `$valid_types`, sem o
  que a criação descartava a pergunta;
- validação de 10 ou 11 dígitos;
- coleta pública com máscara e `format_phone()` no `Responder.php`;
- rótulos nas telas de IA e relatórios.

**URL da API no build** (`429d89b`):

- `ApiService.baseUrl = String.fromEnvironment('API_BASE_URL',
  defaultValue: 'https://painel.sxdata.com.br/api')`.

**Telefone no app** (`083e7de`):

- `lib/utils/phone_format.dart` (`formatPhone`, `isValidPhone`,
  `PhoneInputFormatter`);
- `case 'phone'` no `QuestionWidget`;
- telefone pela metade bloqueia o avanço (`_canAdvanceFromCurrentQuestion`
  e `_handleNext`);
- teste `test/phone_format_test.dart`.

**Versão 3.3.0** (`6a3fb00`):

- `pubspec.yaml` em `3.3.0+21`;
- Configurações passou a ler `PackageInfo` em vez de "1.0.0" fixo.
