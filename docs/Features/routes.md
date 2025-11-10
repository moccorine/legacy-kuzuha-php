# ルート一覧

Legacy Kuzuha PHP BBS の全ルート定義と機能説明

## ルート一覧表

| メソッド | パス | 機能 | 使用クラス | 主要パラメータ |
|---------|------|------|-----------|--------------|
| GET | `/` | メインページ表示 | `Bbs::main()` | `c`, `d`, `p`, `setup` |
| POST | `/` | 新規投稿送信 | `Bbs::main()` | `u`, `t`, `v`, `pc`, `f` |
| GET/POST | `/search` | ログ検索・閲覧 | `Getlog::main()` | `word`, `key`, `ff`, `st`, `et` |
| GET/POST | `/tree` | ツリー表示 | `Bbs\TreeView::main()` | `s`, `c`, `d` |
| GET/POST | `/thread` | スレッド表示 | `Bbs::prtsearchlist()` | `s`, `c`, `d`, `p` |
| GET | `/follow` | 返信フォーム表示 | `Bbs::prtfollow()` | `s`, `ff`, `c`, `d` |
| POST | `/follow` | 返信投稿送信 | `Bbs::main()` | `u`, `t`, `v`, `f`, `ff`, `pc` |
| GET/POST | `/admin` | 管理モード | `Bbs\Admin::main()` | `m`, `v`, `x` |

### レガシーURL（301リダイレクト）

| 旧URL | 新URL | 説明 |
|-------|-------|------|
| `/?m=g` | `/search` | ログ検索 |
| `/?m=tree` | `/tree` | ツリー表示 |
| `/?m=t` | `/thread` | スレッド表示 |
| `/?m=f` | `/follow` | 返信フォーム |
| `/?m=ad` | `/admin` | 管理モード |

## 目次
- [メインページ](#メインページ)
- [検索・ログ閲覧](#検索ログ閲覧)
- [ツリー表示](#ツリー表示)
- [スレッド表示](#スレッド表示)
- [フォローアップ投稿](#フォローアップ投稿)
- [管理モード](#管理モード)
- [レガシーURL対応](#レガシーurl対応)

---

## メインページ

### `GET /`
**機能**: 掲示板のメインページを表示

**処理内容**:
- 最新の投稿一覧を表示
- アクセスカウンター・参加者カウンターの更新
- ユーザー設定（色、表示件数など）の適用
- Cookie からユーザー情報を読み込み

**使用クラス**:
- `Kuzuha\Bbs::main()`
- `Kuzuha\Bbs\ImageBbs::main()` (画像モード時)

**リポジトリ**:
- `AccessCounterRepository` - アクセス数記録
- `ParticipantCounterRepository` - 参加者数記録
- `BbsLogRepository` - メッセージログ読み込み
- `OldLogRepository` - アーカイブログ読み込み

**クエリパラメータ**:
- `c` - カラー設定（Base32エンコード）
- `d` - 表示件数
- `p` - 表示開始位置（投稿ID）
- `setup` - ユーザー設定画面表示フラグ

**レスポンス**:
- HTML（Twig テンプレート: `main/upper.twig`, `main/lower.twig`）
- Cookie 設定（ユーザー名、メールアドレス、カラー設定）

---

### `POST /`
**機能**: 新規投稿の送信

**処理内容**:
1. フォーム入力のサニタイズ
2. バリデーション（`BbsPostValidator`）
   - 投稿停止チェック
   - 管理者専用モードチェック
   - Referer チェック
   - メッセージ形式チェック（文字数、行数）
   - フィールド長チェック（名前、メール、タイトル）
   - 投稿間隔チェック（プロテクトコード検証）
   - 禁止ワードチェック
3. メッセージ保存（`BbsLogRepository`）
4. アーカイブ作成（設定に応じて）
5. 投稿完了ページまたはメインページへリダイレクト

**使用クラス**:
- `Kuzuha\Bbs::main()` → `handlePostMode()`
- `App\Services\BbsPostValidator` - バリデーション
- `App\Services\BbsMessageService` - メッセージ整形

**フォームフィールド**:
- `u` - 投稿者名
- `i` - メールアドレス（スパム対策で使用禁止）
- `t` - タイトル
- `v` - メッセージ本文（必須）
- `l` - URL
- `pc` - プロテクトコード（CSRF対策）
- `m` - モード（`p` = 投稿）
- `f` - フォローアップ先投稿ID（返信時）
- `ff` - フォローアップ元ファイル名（アーカイブからの返信時）

**バリデーションエラー**:
- 投稿停止中
- 管理者専用モード（パスワード不一致）
- 文字数超過
- 行数超過
- 投稿間隔が短すぎる
- 禁止ワード検出
- スパム検出（メールアドレス入力）

**成功時**:
- 投稿完了ページ表示
- Undo Cookie 設定（削除用）
- メインページへ戻る

**特殊ケース**:
- 管理者パスワード入力時 → 管理モードへ遷移
- 二重投稿検出時 → メインページ表示
- レート制限時 → フォーム再表示（プロテクトコード再生成）

---

## 検索・ログ閲覧

### `GET /search` `POST /search`
**機能**: 過去ログの検索とアーカイブ閲覧

**処理内容**:
- キーワード検索（投稿者、タイトル、本文）
- 日付範囲検索
- アーカイブファイル一覧表示
- 検索結果の表示
- HTML形式でのログダウンロード

**使用クラス**:
- `Kuzuha\Getlog::main()`

**リポジトリ**:
- `OldLogRepository` - アーカイブログ読み込み

**クエリパラメータ（GET）**:
- `c` - カラー設定
- `d` - 表示件数

**フォームフィールド（POST）**:
- `word` - 検索キーワード
- `key` - 検索対象（`USER`, `TITLE`, `MSG`）
- `ff` - 検索対象ファイル名
- `st` - 開始日時
- `et` - 終了日時
- `dl` - ダウンロードフラグ

**検索モード**:
1. **アーカイブ一覧**: パラメータなし
2. **キーワード検索**: `word` + `key` 指定
3. **日付範囲検索**: `st` + `et` 指定
4. **ファイル指定検索**: `ff` 指定
5. **HTMLダウンロード**: `dl=1` 指定

**レスポンス**:
- HTML（Twig テンプレート: `log/list.twig`, `log/archivelist.twig`, `log/searchresult.twig`）
- HTML ダウンロード（`log/htmldownload.twig`）

**ログ形式**:
- **DAT形式** (`OLDLOGFMT=1`): CSV形式、日次/月次アーカイブ
- **HTML形式** (`OLDLOGFMT=0`): HTML形式、日次/月次アーカイブ

---

## ツリー表示

### `GET /tree` `POST /tree`
**機能**: スレッド構造をツリー形式で表示

**処理内容**:
- スレッドの親子関係を解析
- ツリー構造で投稿を表示
- 返信の階層表示
- 投稿機能（POST時）

**使用クラス**:
- `Kuzuha\Bbs\TreeView::main()`

**クエリパラメータ**:
- `s` - スレッドID（必須）
- `c` - カラー設定
- `d` - 表示件数
- `p` - 表示開始位置

**POST時の処理**:
- 新規投稿の送信（メインページと同様）
- 投稿完了後、ツリー表示に戻る

**レスポンス**:
- HTML（Twig テンプレート: `tree/upper.twig`, `tree/lower.twig`）

**表示内容**:
- スレッドのルート投稿
- 返信ツリー（インデント表示）
- 各投稿の投稿者、日時、本文
- 返信ボタン、検索ボタン

---

## スレッド表示

### `GET /thread` `POST /thread`
**機能**: 特定スレッドの投稿を時系列で表示

**処理内容**:
- スレッドIDに紐づく投稿を抽出
- 時系列順に表示
- 投稿機能（POST時）

**使用クラス**:
- `Kuzuha\Bbs::prtsearchlist()`
- `Kuzuha\Bbs\ImageBbs::prtsearchlist()` (画像モード時)

**クエリパラメータ**:
- `s` - スレッドID（必須）
- `c` - カラー設定
- `d` - 表示件数
- `p` - 表示開始位置

**処理フロー**:
1. `loadAndSanitizeInput()` - 入力サニタイズ
2. `applyUserPreferences()` - ユーザー設定適用
3. `initializeSession()` - セッション初期化
4. `prtsearchlist()` - スレッド検索・表示

**レスポンス**:
- HTML（Twig テンプレート: `log/topiclist.twig`）

**表示内容**:
- スレッド内の全投稿
- 投稿者、日時、本文
- 返信ボタン、ツリー表示ボタン

---

## フォローアップ投稿

### `GET /follow`
**機能**: 特定投稿への返信フォーム表示

**処理内容**:
1. 返信元投稿の取得
2. 引用文の生成（`QuoteRegex::formatAsQuote()`）
3. 返信フォームの表示

**使用クラス**:
- `Kuzuha\Bbs::prtfollow()`
- `Kuzuha\Bbs\ImageBbs::prtfollow()` (画像モード時)

**クエリパラメータ**:
- `s` - 返信先投稿ID（必須）
- `ff` - 返信元ファイル名（アーカイブからの返信時）
- `c` - カラー設定
- `d` - 表示件数
- `p` - 表示開始位置

**処理フロー**:
1. 投稿IDの検証
2. 管理者専用モードのチェック
3. 返信元投稿の検索（メインログまたはアーカイブ）
4. メッセージの引用整形
5. フォーム表示

**レスポンス**:
- HTML（Twig テンプレート: `follow.twig`）

**フォーム内容**:
- 返信元投稿の表示
- 引用文が自動入力されたメッセージ欄
- タイトル（`＞投稿者名` が自動入力）
- Hidden フィールド（`f`, `ff`, `s`）

---

### `POST /follow`
**機能**: フォローアップ投稿の送信

**処理内容**:
1. フォーム入力のサニタイズ
2. `$_POST['m'] = 'p'` を設定（投稿モード）
3. `$_POST['f']` にフォローアップIDを設定
4. `Bbs::main()` を呼び出し
5. `handlePostMode()` で投稿処理
6. スレッドIDの解決（アーカイブからの返信時）
7. 投稿完了ページ表示

**使用クラス**:
- `Kuzuha\Bbs::main()` → `handlePostMode()`

**フォームフィールド**:
- `u` - 投稿者名
- `t` - タイトル
- `v` - メッセージ本文（引用文含む）
- `f` - フォローアップ先投稿ID
- `ff` - フォローアップ元ファイル名
- `s` - 返信先投稿ID
- `pc` - プロテクトコード

**特殊処理**:
- アーカイブからの返信時、スレッドIDを解決（`resolveThreadFromArchive()`）
- 投稿完了後、`prtputcomplete()` を表示

**レスポンス**:
- 投稿完了ページ（Twig テンプレート: `postcomplete.twig`）

---

## 管理モード

### `GET /admin` `POST /admin`
**機能**: 管理者専用機能

**処理内容**:
- 管理者認証
- メッセージ削除
- パスワード設定
- ログファイル閲覧

**使用クラス**:
- `Kuzuha\Bbs\Admin::main()`

**認証方法**:
- POST パラメータ `u`（パスワード）と `v`（管理者キー）を検証
- `SecurityHelper::verifyAdminPassword()` で認証

**管理機能**:

#### 1. 管理メニュー（デフォルト）
- メッセージ削除モードへのリンク
- パスワード設定へのリンク
- ログファイル閲覧へのリンク

#### 2. メッセージ削除モード（`m=k`）
- 全投稿の一覧表示
- チェックボックスで削除対象を選択
- 削除実行（`m=x`）

#### 3. 削除実行（`m=x`）
- 選択された投稿を削除
- メインログから削除（`BbsLogRepository::deleteMessages()`）
- アーカイブログから削除（`BbsLogRepository::deleteFromArchive()`）
- 関連画像を削除（`deleteImagesFromMessages()`）
- 削除結果を返す

#### 4. パスワード設定（`m=p`）
- 新しいパスワードの入力フォーム表示

#### 5. パスワード暗号化（`m=l`）
- 入力されたパスワードを暗号化
- 暗号化文字列を表示（`.env` に設定）

#### 6. ログファイル閲覧（`m=v`）
- メインログファイルの内容を表示
- 生のCSV形式で表示

**クエリパラメータ**:
- `m` - モード（`k`, `x`, `p`, `l`, `v`）
- `v` - 管理者キー（認証用）
- `x` - 削除対象投稿ID配列（削除実行時）

**レスポンス**:
- HTML（Twig テンプレート: `admin/menu.twig`, `admin/delete-list.twig`, `admin/setpass.twig`, `admin/pass.twig`）

**セキュリティ**:
- 管理者パスワード検証（`ADMINPOST`）
- 管理者キー検証（`ADMINKEY`）
- 両方が一致しないと管理機能にアクセス不可

---

## レガシーURL対応

### ミドルウェア: Legacy URL Redirect
**機能**: 旧形式のURL（`?m=` パラメータ）を新形式にリダイレクト

**処理内容**:
- `?m=` パラメータを検出
- 対応する新しいパスにリダイレクト（301 Moved Permanently）

**URL マッピング**:
```
/?m=g      → /search
/?m=tree   → /tree
/?m=t      → /thread
/?m=f      → /follow
/?m=ad     → /admin
```

**例**:
```
旧: /?m=f&s=5&c=48&d=40&p=5
新: /follow?s=5&c=48&d=40&p=5
```

**実装**:
```php
$app->add(function (Request $request, $handler) {
    $queryParams = $request->getQueryParams();
    
    if (isset($queryParams['m'])) {
        $m = $queryParams['m'];
        $pathMap = [
            'g' => '/search',
            'tree' => '/tree',
            't' => '/thread',
            'f' => '/follow',
            'ad' => '/admin',
        ];
        
        if (isset($pathMap[$m])) {
            unset($queryParams['m']);
            $newQuery = http_build_query($queryParams);
            $newPath = $pathMap[$m] . ($newQuery ? '?' . $newQuery : '');
            
            return $handler->handle($request)
                ->withStatus(301)
                ->withHeader('Location', $newPath);
        }
    }
    
    return $handler->handle($request);
});
```

**メリット**:
- 旧URLからのリンクが機能し続ける
- SEO対策（301リダイレクト）
- 段階的な移行が可能

---

## 共通処理

### すべてのルートで実行される処理

#### 1. HTTPヘッダー設定（ミドルウェア）
```php
Content-Type: text/html; charset=UTF-8
X-XSS-Protection: 1; mode=block
Content-Security-Policy: frame-ancestors *;
```

#### 2. 入力処理
- `loadAndSanitizeInput()` - POST/GET データの取得とサニタイズ
- `applyUserPreferences()` - ユーザー設定の適用
- `initializeSession()` - セッション初期化

#### 3. Cookie 処理
- ユーザー名、メールアドレス、カラー設定の読み込み
- 投稿後の Cookie 設定（`CookieService::applyPendingCookies()`）

#### 4. 出力バッファリング
- `ob_start()` で出力をバッファリング
- `ob_get_clean()` でバッファを取得
- レスポンスボディに書き込み

---

## ルート設計の特徴

### RESTful 設計
- リソース指向のURL（`/search`, `/tree`, `/follow`）
- HTTP メソッドの使い分け（GET: 表示、POST: 送信）
- クエリパラメータでフィルタリング

### 後方互換性
- レガシーURL（`?m=`）を301リダイレクト
- 既存のリンクが機能し続ける

### セキュリティ
- CSRF対策（プロテクトコード）
- XSS対策（入力サニタイズ、HTMLエスケープ）
- 管理者認証（パスワード + キー）
- Referer チェック
- レート制限（投稿間隔チェック）

### パフォーマンス
- 出力バッファリング
- gzip圧縮（設定により）
- アクセスカウンター・参加者カウンターの効率的な更新

---

## 今後の改善予定

### Phase 1: アクション抽出
- `main()` からアクションメソッドを抽出
- ルートが直接アクションを呼ぶ
- 内部ルーティングの削除

### Phase 2: Controller 層
- `BbsController`, `AdminController` の作成
- MVC アーキテクチャへの移行
- ミドルウェアの活用

### Phase 3: API化
- JSON レスポンスのサポート
- REST API エンドポイントの追加
- フロントエンドとの分離

詳細は `docs/architecture/routing-improvement-analysis.md` を参照。
