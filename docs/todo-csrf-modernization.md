# TODO: CSRF対策とPCODE置き換え

## 概要

現在のプロテクトコード (PCODE) をモダンなCSRF対策ライブラリに置き換える。

## 現状の問題点

- DES crypt（レガシー暗号化）を使用
- タイムスタンプが平文で見える
- 有効期限がない（古いPCODEが永久に有効）
- ソルトが固定（`ADMINPOST`の末尾4文字）
- 二重投稿防止がログ全スキャン（パフォーマンス問題）
- 4文字の暗号コードのみ保存（衝突の可能性）

## 推奨ライブラリ

### 1. CSRF対策: `slim/csrf`

**インストール:**
```bash
composer require slim/csrf
```

**理由:**
- Slim Framework公式
- PSR-7/PSR-15対応
- 既存アーキテクチャに適合
- 軽量で実績あり

**実装箇所:**
- `src/routes.php` - ミドルウェア追加
- `src/Kuzuha/Bbs.php` - PCODE生成/検証を削除
- `resources/views/components/form.twig` - CSRFトークン埋め込み
- `src/Utils/SecurityHelper.php` - generateProtectCode/verifyProtectCode削除

### 2. 二重投稿防止: `symfony/lock`

**インストール:**
```bash
composer require symfony/lock
```

**理由:**
- 既にSymfony Translationを使用中
- ファイルロック、Redis、Memcached対応
- 確実な排他制御

**実装箇所:**
- `src/Kuzuha/Bbs.php` - putmessage()でロック取得
- ログファイル全スキャンを削除

### 3. レート制限 (オプション): `symfony/rate-limiter`

**インストール:**
```bash
composer require symfony/rate-limiter
```

**理由:**
- 投稿間隔制限（MINPOSTSEC）を置き換え
- IPベース、ユーザーベース制限
- Redis/Memcachedでスケーラブル

## 実装計画

### Phase 1: CSRF対策の導入 (優先度: 高)

**タスク:**
- [ ] `slim/csrf`をインストール
- [ ] `src/routes.php`にCSRFミドルウェア追加
- [ ] `src/Kuzuha/Bbs.php`のPCODE生成を削除
- [ ] `resources/views/components/form.twig`をCSRFトークンに変更
- [ ] 全フォームでCSRFトークン検証
- [ ] テスト作成（CSRF検証、トークン生成）

**影響範囲:**
- `src/Utils/SecurityHelper.php`
- `src/Kuzuha/Bbs.php` (buildPostMessage, validatePost)
- `src/Kuzuha/Treeview.php`
- `src/Kuzuha/Imagebbs.php`
- `resources/views/components/form.twig`
- `resources/views/follow.twig`

**後方互換性:**
- PCODEフィールド（`pc`）を段階的に削除
- 既存ログファイルのPCODEフィールドは残す（読み取り専用）

### Phase 2: 二重投稿防止の改善 (優先度: 中)

**タスク:**
- [ ] `symfony/lock`をインストール
- [ ] `src/Kuzuha/Bbs.php`のputmessage()にロック機構追加
- [ ] ログファイル全スキャンを削除
- [ ] ロックストレージ選択（ファイル/Redis）
- [ ] テスト作成（同時投稿、ロック解放）

**影響範囲:**
- `src/Kuzuha/Bbs.php` (putmessage)
- `conf.php` (ロック設定追加)

**設定例:**
```php
'LOCK_STORE' => 'flock', // flock, redis, memcached
'LOCK_TTL' => 30, // ロック有効期限（秒）
```

### Phase 3: レート制限の導入 (優先度: 低)

**タスク:**
- [ ] `symfony/rate-limiter`をインストール
- [ ] IPベースレート制限を実装
- [ ] MINPOSTSEC、SPTIMEをレート制限に置き換え
- [ ] Redis/Memcachedストレージ設定
- [ ] テスト作成（レート制限、リセット）

**影響範囲:**
- `src/Kuzuha/Bbs.php` (validatePost)
- `conf.php` (レート制限設定)

**設定例:**
```php
'RATE_LIMIT' => [
    'posts_per_minute' => 12, // 1分間に12投稿
    'posts_per_hour' => 100,  // 1時間に100投稿
    'storage' => 'redis',     // redis, memcached, file
],
```

## 削除予定のコード

### `src/Utils/SecurityHelper.php`
```php
// 削除
public static function generateProtectCode(bool $limithost = true): string
public static function verifyProtectCode(string $pcode, bool $limithost = true): ?int
```

### `src/Kuzuha/Bbs.php`
```php
// 削除
$pcode = SecurityHelper::generateProtectCode();
$timestamp = SecurityHelper::verifyProtectCode($this->form['pc'], $limithost);

// 削除（二重投稿チェック）
if ($message['PCODE'] == $items[2]) {
    $posterr = 2;
    break;
}
```

### `resources/views/components/form.twig`
```twig
{# 削除 #}
<input type="hidden" name="pc" value="{{ PCODE }}" />

{# 追加 #}
<input type="hidden" name="{{ csrf_name }}" value="{{ csrf_name_value }}">
<input type="hidden" name="{{ csrf_value }}" value="{{ csrf_value_value }}">
```

## マイグレーション戦略

### ステップ1: 並行運用
- 新しいCSRFトークンを追加
- 既存のPCODEも検証（後方互換性）
- 両方が有効な状態で運用

### ステップ2: PCODE検証を警告のみに
- PCODE検証失敗をエラーではなく警告に
- ログに記録して監視

### ステップ3: PCODE完全削除
- PCODE生成を停止
- PCODE検証を削除
- フォームからPCODEフィールド削除

## テスト計画

### CSRF対策テスト
- [ ] 有効なCSRFトークンで投稿成功
- [ ] 無効なCSRFトークンで投稿失敗
- [ ] トークンなしで投稿失敗
- [ ] トークン再利用で失敗（ワンタイムトークン）

### 二重投稿防止テスト
- [ ] 同時投稿で片方が失敗
- [ ] ロック解放後に投稿成功
- [ ] ロックタイムアウト処理

### レート制限テスト
- [ ] 制限内で投稿成功
- [ ] 制限超過で投稿失敗
- [ ] 時間経過後にリセット

## 設定ファイル変更

### `conf.php`
```php
// 削除予定
'MINPOSTSEC' => 5,
'SPTIME' => 60,
'IPREC' => 1,

// 追加予定
'CSRF' => [
    'storage' => 'session', // session, cookie
    'strength' => 32,       // トークン長
],
'LOCK' => [
    'store' => 'flock',     // flock, redis, memcached
    'ttl' => 30,            // ロック有効期限（秒）
],
'RATE_LIMIT' => [
    'enabled' => true,
    'posts_per_minute' => 12,
    'posts_per_hour' => 100,
    'storage' => 'file',    // file, redis, memcached
],
```

## 参考資料

- [Slim CSRF Documentation](https://github.com/slimphp/Slim-Csrf)
- [Symfony Lock Component](https://symfony.com/doc/current/components/lock.html)
- [Symfony Rate Limiter](https://symfony.com/doc/current/rate_limiter.html)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [docs/protect-code-specification-ja.md](./protect-code-specification-ja.md) - 現在のPCODE仕様

## 見積もり

- **Phase 1 (CSRF対策)**: 4-6時間
- **Phase 2 (二重投稿防止)**: 2-3時間
- **Phase 3 (レート制限)**: 2-3時間
- **テスト作成**: 3-4時間
- **合計**: 11-16時間

## 優先度

1. **Phase 1 (CSRF対策)** - 高: セキュリティ上重要
2. **Phase 2 (二重投稿防止)** - 中: パフォーマンス改善
3. **Phase 3 (レート制限)** - 低: 既存機能で代替可能

## 注意事項

- 本番環境では段階的にロールアウト
- ログファイルのPCODEフィールドは削除しない（既存データ保持）
- セッションストレージが必要（現在はクッキーのみ）
- Redis/Memcachedは本番環境で推奨（開発環境はファイルベース）
