# Webapp vs Bbs クラス設計

## 概要

```
Webapp (基底クラス - 480行)
  ↑
  └── Bbs (Webappを継承 - 1621行)
```

## Webapp (基底クラス)

**目的:** Webアプリケーションの基盤機能 - リクエスト処理、セッション管理、メッセージ表示

**責務:**
- フォーム入力処理 (`procForm`)
- ユーザーセッション管理 (`setusersession`)
- エラー処理 (`prterror`)
- メッセージデータ準備 (`prepareMessageForDisplay`)
- メッセージ表示 (`renderMessage`)
- ログファイル操作 (`loadmessage`, `getmessage`)
- テンプレート表示 (`renderTwig`, `refcustom`)

**主要メソッド:**
- `procForm()` - POST/GETリクエスト処理
- `setusersession()` - ユーザーセッション初期化（ホスト、Cookie、クエリパラメータ）
- `prepareMessageForDisplay($message, $mode, $tlog)` - 生メッセージデータを表示用に変換
- `renderMessage($message, $mode, $tlog)` - Twig経由でメッセージHTML生成
- `loadmessage($logfilename)` - ログファイル読み込み
- `getmessage($logline)` - ログ行をメッセージ配列にパース
- `renderTwig($template, $data)` - Twigテンプレート表示

**プライベートヘルパー:**
- `processReferenceLinks($msg, $mode)` - 参照リンク変換
- `processQuotes($msg)` - 引用テキスト整形
- `processImages($msg)` - 画像表示処理
- `buildActionButtons($message, $mode, $tlog)` - アクションボタンURL生成

## Bbs (掲示板クラス)

**目的:** 掲示板のビジネスロジック - 投稿、検索、スレッド管理

**責務:**
- 掲示板メイン表示 (`main`, `prtmain`)
- 投稿処理 (`handlePostMode`, `validatePost`, `buildPostMessage`)
- 検索機能 (`prtsearchlist`, `msgsearchlist`, `searchmessage`)
- 返信投稿 (`prtfollow`)
- 新規投稿フォーム (`prtnewpost`)
- 投稿完了画面 (`prtputcomplete`)
- 取り消し機能 (`prtundo`)
- カスタム設定 (`prtcustom`, `setcustom`)
- 統計情報 (`getStatsData`)
- フォームデータ準備 (`getFormData`)

**主要メソッド:**
- `main()` - メインエントリーポイント、適切なハンドラーへルーティング
- `prtmain($mode)` - 掲示板表示
- `handlePostMode()` - 投稿処理
- `validatePost($limithost)` - 投稿データ検証（複数のプライベートバリデーター）
- `buildPostMessage()` - 保存用メッセージデータ構築
- `prtsearchlist($mode)` - 検索インターフェース表示
- `msgsearchlist($mode)` - 検索実行と結果表示
- `prtfollow($retry)` - 返信投稿フォーム表示
- `prtnewpost($retry)` - 新規投稿フォーム表示
- `getdispmessage()` - ページネーション付きメッセージ取得

**プライベートヘルパー:**
- `validatePostingEnabled()` - 投稿可否チェック
- `validateAdminOnly()` - 管理者専用モードチェック
- `validateReferer()` - HTTPリファラー検証
- `validateMessageFormat()` - メッセージ構造検証
- `validateFieldLengths()` - フィールド長検証
- `validatePostInterval($limithost)` - 投稿間隔チェック
- `validateProhibitedWords()` - 禁止ワードチェック
- `extractFormData()` - フォームデータ抽出
- `processUsername($username, $message)` - ユーザー名とトリップ処理
- `processTripCode($username)` - トリップコード生成
- `processMessageContent($message, $url)` - メッセージ内容処理
- `attachReference($message)` - メッセージに参照を付加

## 現在の問題点

### 責務分離が不明瞭

**問題領域:**

1. **メッセージ表示ロジックの分散:**
   - `Webapp::prepareMessageForDisplay()` - データ準備
   - `Webapp::renderMessage()` - テンプレート表示
   - `Bbs::getdispmessage()` - ページネーション付きメッセージ取得
   - どのクラスが何を担当すべきか不明瞭

2. **フォーム処理の重複:**
   - `Webapp::procForm()` - 汎用フォーム処理
   - `Bbs::getFormData()` - BBS固有のフォームデータ
   - `Bbs::extractFormData()` - 投稿フォーム抽出
   - 責務が重複

3. **テンプレート表示:**
   - `Webapp::renderTwig()` - 汎用テンプレート表示
   - `Webapp::refcustom()` - カスタムテンプレート参照
   - `Bbs::prtmain()`, `prtfollow()` など - 特定ページ表示
   - 抽象度が混在

4. **バリデーションロジック:**
   - 全てのバリデーションが`Bbs`クラスに集中
   - 汎用的なWeb検証は`Webapp`にあるべきでは？

## 現在のルーティング構造

```
routes.php (Slim Framework)
├── GET/POST /           → Bbs::main() / Imagebbs::main()
├── GET/POST /search     → Getlog::main()
├── GET/POST /tree       → Treeview::main()
├── GET/POST /thread     → Bbs::prtsearchlist()
├── GET/POST /follow     → Bbs::prtfollow()
└── GET/POST /admin      → Bbsadmin::main()
```

**現在のクラス構成:**
```
Webapp (基底)
  ↑
  ├── Bbs (掲示板メイン)
  ├── Imagebbs (画像掲示板)
  ├── Bbsadmin (管理画面)
  ├── Getlog (ログ検索)
  └── Treeview (ツリー表示)
```

**問題点:**
- 各クラスが`main()`を持つが、内部で`prtmain()`, `prtfollow()`など複数の画面を処理
- ルーティングとメソッドの対応が不明瞭
- `Bbs::main()`内で`$_GET['m']`によるサブルーティングが残存

## 推奨事項: ルーティングベースのコントローラー分割

### 推奨構造

```
routes.php
├── GET/POST /           → BbsController::index()
├── POST /post           → BbsController::post()
├── GET /follow          → BbsController::follow()
├── GET /thread          → ThreadController::show()
├── GET /tree            → TreeController::show()
├── GET/POST /search     → SearchController::index()
└── GET/POST /admin      → AdminController::index()

Controllers/
├── BbsController.php
│   ├── index()    - 掲示板メイン表示
│   ├── post()     - 投稿処理
│   └── follow()   - 返信フォーム
├── ThreadController.php
│   └── show()     - スレッド表示
├── TreeController.php
│   └── show()     - ツリー表示
├── SearchController.php
│   └── index()    - ログ検索
└── AdminController.php
    └── index()    - 管理画面

Services/
├── MessageService.php      - メッセージ整形
├── LogService.php          - ログ読み書き
├── ValidationService.php   - 投稿検証
├── PostService.php         - 投稿処理
└── SearchService.php       - 検索処理

Webapp (基底)
├── Request/Session管理
├── エラー処理
└── テンプレート表示
```

### 段階的移行計画

**フェーズ1: サービス抽出（既存構造維持）**
1. `MessageService` - `prepareMessageForDisplay`等を抽出
2. `LogService` - `loadmessage`, `getmessage`を抽出
3. `ValidationService` - 8個のバリデーションメソッドを抽出
4. 既存クラスからサービスを呼び出すように変更

**フェーズ2: コントローラー整理**
1. `Bbs::main()`内のサブルーティングを削除
2. 各ルートに対応するメソッドを明確化
3. `prtmain()`, `prtfollow()`等を`index()`, `follow()`にリネーム

**フェーズ3: コントローラー分割（オプション）**
1. `ThreadController`, `TreeController`を独立
2. `SearchController`を`Getlog`から移行
3. `AdminController`を`Bbsadmin`から移行

### 最小限の推奨アクション

**今すぐ実施:**
1. **MessageService抽出** - 今リファクタリングした部分
2. **ValidationService抽出** - テストしやすくする
3. **ドキュメント整備** - 各クラスの責務を明確化

**次のステップ:**
1. `Bbs::main()`のサブルーティング削除
2. ルートとメソッドの1対1対応
3. 残りのサービス抽出

## 次のステップ

1. **MessageService作成** ← まずここから
2. ValidationService作成
3. LogService作成
4. 既存クラスをスリム化
5. ルーティングとメソッドの対応を明確化
