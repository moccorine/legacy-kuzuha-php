# テンプレート構造分析: Layout + Component パターンとの比較

## 現在のテンプレート構造

```
resources/views/
├── layout/
│   ├── base.twig              # 基本レイアウト（extends可能）
│   └── base_header.twig       # ヘッダーのみ（extendsなし）
├── components/
│   ├── message.twig           # メッセージコンポーネント
│   ├── form.twig              # フォームコンポーネント
│   ├── stats.twig             # 統計コンポーネント
│   ├── error.twig             # エラーコンポーネント
│   └── tree_customstyle.twig  # ツリーカスタムスタイル
├── main/
│   ├── upper.twig             # メインページ上部（独立HTML）
│   └── lower.twig             # メインページ下部（独立HTML）
├── tree/
│   ├── upper.twig             # ツリー上部（extends base.twig）
│   └── lower.twig             # ツリー下部（独立HTML）
├── log/
│   ├── archivelist.twig       # アーカイブリスト（extends base.twig）
│   ├── searchresult.twig      # 検索結果（extends base.twig）
│   ├── oldlog_header.twig     # 旧ログヘッダー（独立HTML）
│   └── oldlog_footer.twig     # 旧ログフッター（独立HTML）
└── [その他のページ]
    ├── follow.twig            # 返信ページ（extends base.twig）
    ├── newpost.twig           # 新規投稿（extends base.twig）
    ├── postcomplete.twig      # 投稿完了（extends base.twig）
    └── ...
```

## 一般的な Layout + Component パターン

```
理想的な構造:
├── layouts/
│   └── base.twig              # 全ページ共通レイアウト
├── components/
│   ├── message.twig           # 再利用可能なコンポーネント
│   ├── form.twig
│   ├── navigation.twig
│   └── footer.twig
└── pages/
    ├── index.twig             # extends layout, include components
    ├── follow.twig
    └── search.twig
```

## 評価: できているところ ✅

### 1. **コンポーネントの分離（良好）**
```twig
{# components/message.twig - 再利用可能 #}
<span class="ngline">
  <div class="m" id="m{{ POSTID }}">
    ...
  </div>
</span>
```
- ✅ `message.twig` - メッセージ表示ロジックが独立
- ✅ `form.twig` - フォームコンポーネントが独立
- ✅ `stats.twig` - 統計表示が独立
- ✅ PHPから`renderMessage()`で呼び出し可能

### 2. **レイアウト継承の活用（部分的に良好）**
```twig
{# follow.twig #}
{% extends 'layout/base.twig' %}
{% block content %}
  ...
{% endblock %}
```
- ✅ `follow.twig`, `newpost.twig`, `postcomplete.twig` - 正しく継承
- ✅ `log/`, `admin/` 配下のページ - 正しく継承
- ✅ `tree/upper.twig` - 正しく継承

### 3. **データとビューの分離（良好）**
```php
// PHP側でデータ準備
$data = array_merge($this->config, $this->session, [
    'TRANS_FOLLOWUP_POST' => Translator::trans('followup_post'),
    ...
]);
echo $this->renderTwig('follow.twig', $data);
```
- ✅ PHPでデータ準備、Twigで表示のみ
- ✅ 翻訳キーの分離

## 問題点: ずれているところ ❌

### 1. **upper/lower パターンの不統一（重大）**

**問題:**
```php
// Bbs.php - メインページ
echo $this->renderTwig('main/upper.twig', $data);  // ヘッダー出力
foreach ($logdatadisp as $msgdata) {
    print $this->renderMessage($this->getmessage($msgdata), 0, 0);  // PHP側でループ
}
echo $this->renderTwig('main/lower.twig', $data);  // フッター出力
```

**なぜ問題か:**
- ❌ `main/upper.twig` は完全なHTML（`<!DOCTYPE>`から`<body>`まで）
- ❌ `main/lower.twig` も完全なHTML（`</body></html>`まで）
- ❌ レイアウト継承を使っていない
- ❌ PHP側でループ処理（ビューロジックの漏出）
- ❌ 3つのテンプレートに分割（upper, messages, lower）

**理想的な形:**
```twig
{# main/index.twig #}
{% extends 'layout/base.twig' %}

{% block content %}
  {# ヘッダー部分 #}
  <hr />
  ...
  
  {# メッセージループ #}
  {% for message in messages %}
    {% include 'components/message.twig' with message %}
  {% endfor %}
  
  {# フッター部分 #}
  <p class="msgmore">{{ MSGMORE }}</p>
  ...
{% endblock %}
```

### 2. **base_header.twig の存在（不要）**

**問題:**
```twig
{# base_header.twig - base.twigとほぼ同じ内容 #}
<!DOCTYPE html>
<html>
  <head>...</head>
  <body>
    <a id="top"></a>
{# ここで終わる - blockなし #}
```

- ❌ `base.twig`と重複
- ❌ 継承不可（blockがない）
- ❌ 使用箇所が不明瞭

**対策:** 削除して`base.twig`に統一

### 3. **oldlog_header/footer の分離（非推奨）**

**問題:**
```
oldlog_header.twig  # HTMLの前半
[PHP側でコンテンツ出力]
oldlog_footer.twig  # HTMLの後半
```

- ❌ HTMLが3箇所に分散
- ❌ メンテナンス困難
- ❌ レイアウト継承を使っていない

**理想:**
```twig
{# log/oldlog.twig #}
{% extends 'layout/base.twig' %}
{% block content %}
  {% for item in items %}
    ...
  {% endfor %}
{% endblock %}
```

### 4. **tree/lower.twig の不統一**

**問題:**
```
tree/upper.twig  # extends base.twig ✅
tree/lower.twig  # 独立HTML（</body></html>のみ） ❌
```

- ❌ upperは継承、lowerは独立
- ❌ 一貫性がない

## 推奨される修正

### 優先度1: main/upper + lower の統合

**Before:**
```php
echo $this->renderTwig('main/upper.twig', $data);
foreach ($logdatadisp as $msgdata) {
    print $this->renderMessage($this->getmessage($msgdata), 0, 0);
}
echo $this->renderTwig('main/lower.twig', $data);
```

**After:**
```php
$messages = array_map(
    fn($msgdata) => $this->prepareMessageForDisplay($this->getmessage($msgdata), 0, 0),
    $logdatadisp
);
echo $this->renderTwig('main/index.twig', array_merge($data, ['messages' => $messages]));
```

```twig
{# main/index.twig #}
{% extends 'layout/base.twig' %}

{% block content %}
  {# upper部分の内容 #}
  <hr />
  ...
  
  {# メッセージ表示 #}
  {% for message in messages %}
    {% include 'components/message.twig' with message %}
  {% endfor %}
  
  {# lower部分の内容 #}
  <p class="msgmore">{{ MSGMORE }}</p>
  ...
{% endblock %}
```

### 優先度2: tree/lower の修正

```twig
{# tree/index.twig #}
{% extends 'layout/base.twig' %}

{% block content %}
  {# upper部分 #}
  ...
  
  {# ツリー表示 #}
  {% for item in tree_items %}
    ...
  {% endfor %}
  
  {# lower部分 #}
  ...
{% endblock %}
```

### 優先度3: oldlog の統合

```twig
{# log/oldlog.twig #}
{% extends 'layout/base.twig' %}

{% block content %}
  {% for message in messages %}
    {% include 'components/message.twig' with message %}
  {% endfor %}
{% endblock %}
```

### 優先度4: base_header.twig の削除

- `base.twig`に統一
- 使用箇所を`base.twig`に置き換え

## レンダリングパターンの分類

### パターン1: 完結型（統一されている） ✅

**特徴:** 1つのテンプレートで完結、`exit()`で終了

```php
// prterror - エラー表示して終了
public function prterror($err_message) {
    $data = [...];
    echo View::getInstance()->render('error.twig', $data);
    exit();  // ここで終了
}
```

**使用例:**
- `error.twig` - エラー表示（`extends base.twig`）
- `postcomplete.twig` - 投稿完了（`extends base.twig`）
- `undocomplete.twig` - 削除完了（`extends base.twig`）
- `follow.twig` - 返信フォーム（`extends base.twig`）
- `newpost.twig` - 新規投稿フォーム（`extends base.twig`）
- `custom.twig` - カスタム設定（`extends base.twig`）

**評価:** ✅ 理想的なパターン
- 1テンプレート = 1画面
- レイアウト継承を使用
- データ準備 → レンダリング → 終了

### パターン2: 分割型（統一されていない） ❌

**特徴:** 複数のテンプレートを順次出力、PHP側でループ

```php
// prtmain - メイン画面表示
echo $this->renderTwig('main/upper.twig', $data);  // ヘッダー
foreach ($logdatadisp as $msgdata) {                // PHP側でループ
    print $this->renderMessage(...);
}
echo $this->renderTwig('main/lower.twig', $data);  // フッター
```

**使用例:**
- `main/upper.twig` + `main/lower.twig` - メイン画面
- `tree/upper.twig` + `tree/lower.twig` - ツリー表示
- `log/oldlog_header.twig` + `log/oldlog_footer.twig` - 旧ログ

**評価:** ❌ 非推奨パターン
- HTMLが複数箇所に分散
- レイアウト継承を使わない（または部分的）
- PHP側でビューロジック（ループ）
- メンテナンス困難

### パターン3: コンポーネント型（統一されている） ✅

**特徴:** 再利用可能な部品、他のテンプレートから呼び出し

```php
// renderMessage - メッセージコンポーネント
public function renderMessage($message, $mode = 0, $tlog = '') {
    $message = $this->prepareMessageForDisplay($message, $mode, $tlog);
    return $this->renderTwig('components/message.twig', $message);
}
```

**使用例:**
- `components/message.twig` - メッセージ表示
- `components/form.twig` - フォーム
- `components/stats.twig` - 統計情報
- `components/error.twig` - エラーメッセージ（単体でも使用）

**評価:** ✅ 理想的なパターン
- 再利用可能
- 単一責任
- データ準備とレンダリングが分離

## パターン別の使用状況

| メソッド | パターン | テンプレート | 評価 |
|---------|---------|------------|------|
| `prterror()` | 完結型 | `error.twig` | ✅ |
| `prtfollow()` | 完結型 | `follow.twig` | ✅ |
| `prtnewpost()` | 完結型 | `newpost.twig` | ✅ |
| `prtputcomplete()` | 完結型 | `postcomplete.twig` | ✅ |
| `prtundo()` | 完結型 | `undocomplete.twig` | ✅ |
| `prtcustom()` | 完結型 | `custom.twig` | ✅ |
| `prtsearchlist()` | 完結型 | `searchlist.twig` | ✅ |
| `prtmain()` | 分割型 | `main/upper + lower` | ❌ |
| `Treeview::main()` | 分割型 | `tree/upper + lower` | ❌ |
| `Getlog::*()` | 分割型 | `log/*_header + footer` | ❌ |
| `renderMessage()` | コンポーネント | `components/message.twig` | ✅ |

## 統一すべき方向性

### 推奨: パターン1（完結型）に統一

**理由:**
1. 1画面 = 1テンプレート（わかりやすい）
2. レイアウト継承で共通部分を管理
3. ビューロジックはTwig側に集約
4. テストしやすい

**修正方針:**

```php
// Before: 分割型
echo $this->renderTwig('main/upper.twig', $data);
foreach ($logdatadisp as $msgdata) {
    print $this->renderMessage(...);
}
echo $this->renderTwig('main/lower.twig', $data);

// After: 完結型
$messages = array_map(fn($m) => $this->prepareMessageForDisplay(...), $logdatadisp);
echo $this->renderTwig('main/index.twig', array_merge($data, ['messages' => $messages]));
```

```twig
{# main/index.twig #}
{% extends 'layout/base.twig' %}

{% block content %}
  {# ヘッダー部分 #}
  ...
  
  {# メッセージ表示 #}
  {% for message in messages %}
    {% include 'components/message.twig' with message %}
  {% endfor %}
  
  {# フッター部分 #}
  ...
{% endblock %}
```

## まとめ

### ✅ できていること
1. コンポーネントの分離（message, form, stats）
2. 多くのページでレイアウト継承を使用
3. データとビューの分離

### ❌ 改善が必要なこと
1. **main/upper + lower パターン** - 最優先で修正
2. **tree/lower の不統一** - 継承に統一
3. **oldlog の分離** - 1つのテンプレートに統合
4. **base_header.twig の重複** - 削除
5. **PHP側でのループ処理** - Twig側に移動

### 次のアクション
1. `main/index.twig`を作成してupper/lowerを統合
2. PHP側のループをTwig側に移動
3. 他のupper/lowerパターンも同様に修正
