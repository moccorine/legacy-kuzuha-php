# TODO

## エラーハンドリングの改善

### HTTPステータスコード対応
- [ ] `prterror()`にステータスコード引数を追加（デフォルト400）
- [ ] エラー種別ごとに適切なステータスコードを設定
  - 403: 管理者専用、投稿停止
  - 404: 投稿が見つからない、ファイルが見つからない
  - 422: バリデーションエラー（スパム、禁止ワード、文字数超過）
  - 500: サーバーエラー
- [ ] ステータスコード別のエラーテンプレート作成（オプション）
  - `errors/403.twig`
  - `errors/404.twig`
  - `errors/422.twig`
  - `errors/500.twig`

### 将来的な改善（大規模リファクタリング後）
- [ ] 例外ベースのエラーハンドリングに移行
- [ ] グローバル例外ハンドラーの実装
- [ ] カスタム例外クラスの作成

## テンプレート構造の改善

### 優先度1: main/upper + lower の統合
- [ ] `main/index.twig`を作成
- [ ] メッセージループをPHP側からTwig側に移動
- [ ] `Bbs::prtmain()`を修正

### 優先度2: tree の統合
- [ ] `tree/index.twig`を作成
- [ ] `tree/upper.twig` + `tree/lower.twig`を統合

### 優先度3: oldlog の統合
- [ ] `log/oldlog.twig`を作成
- [ ] `oldlog_header.twig` + `oldlog_footer.twig`を統合

### 優先度4: 重複削除
- [ ] `base_header.twig`を削除、`base.twig`に統一

## サービス層の抽出

### フェーズ1: サービス作成
- [ ] `MessageService` - メッセージ整形処理
- [ ] `ValidationService` - バリデーション処理
- [ ] `LogService` - ログ読み書き

### フェーズ2: 既存クラスのスリム化
- [ ] `Webapp`からサービスを呼び出すように変更
- [ ] `Bbs`からサービスを呼び出すように変更

## ファイル操作のRepository化

### 優先度1: LogRepository の作成
- [ ] `LogRepositoryInterface`を定義
  - [ ] `append(array $message): void` - メッセージ追加
  - [ ] `getAll(): array` - 全メッセージ取得
  - [ ] `getRange(int $start, int $end): array` - 範囲指定取得
  - [ ] `findById(int $postId): ?array` - ID検索
  - [ ] `deleteById(int $postId): bool` - ID削除
  - [ ] `count(): int` - メッセージ数取得
  - [ ] `search(array $criteria): array` - 条件検索
- [ ] `LogFileRepository`を実装
- [ ] `bbs.log`への全ての`fopen/fwrite`操作を移行
- [ ] ユニットテスト作成

### 優先度2: ArchiveRepository の作成
- [ ] `ArchiveRepositoryInterface`を定義
  - [ ] `archive(array $message, string $archiveKey): void` - アーカイブ追加
  - [ ] `getArchive(string $archiveKey): array` - アーカイブ取得
  - [ ] `listArchives(): array` - アーカイブ一覧
  - [ ] `searchInArchive(string $archiveKey, array $criteria): array` - アーカイブ内検索
  - [ ] `deleteArchive(string $archiveKey): bool` - アーカイブ削除
- [ ] `ArchiveFileRepository`を実装
- [ ] `archives/*.dat`への全ての`fopen/fwrite`操作を移行
- [ ] ユニットテスト作成

### 優先度3: SessionRepository の作成
- [ ] `SessionRepositoryInterface`を定義
  - [ ] `get(string $key, mixed $default = null): mixed` - 値取得
  - [ ] `set(string $key, mixed $value): void` - 値設定
  - [ ] `has(string $key): bool` - 存在確認
  - [ ] `delete(string $key): void` - 削除
  - [ ] `all(): array` - 全データ取得
  - [ ] `clear(): void` - 全削除
- [ ] `CookieSessionRepository`を実装
- [ ] `$_COOKIE`, `$_SESSION`への直接アクセスを置き換え
- [ ] ユニットテスト作成

### 検討事項
- [ ] LogRepositoryに`transaction()`メソッドが必要か検討
- [ ] ArchiveRepositoryの日次/月次切り替えロジックをどこに配置するか
- [ ] SessionRepositoryのセキュリティ設定（httpOnly, secure, sameSite）
- [ ] 各Repositoryのキャッシュ戦略
- [ ] エラーハンドリング方針（例外 vs 戻り値）

### 移行対象のファイル操作
- [ ] `Bbs.php` - `fopen($this->config['LOGFILENAME'], 'rb+')`
- [ ] `Bbs.php` - `fopen($oldlogfilename, 'ab')`
- [ ] `Bbs.php` - `fopen($this->config['OLDLOGFILEDIR'] . $this->form['ff'], 'rb')`
- [ ] `Webapp.php` - `loadmessage()`, `getmessage()`のファイル操作
- [ ] その他全ての`fopen/fwrite/file_get_contents/file_put_contents`

## その他

- [ ] YouTube URL展開機能の実装を検討（クライアントサイドまたはサーバーサイド）
