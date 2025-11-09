# ブラウザーテストガイド

## 前提条件

Dockerコンテナが起動していること:
```bash
docker-compose up -d
```

## テスト手順

### 1. メインページアクセス

**URL:** http://localhost:8080/

**確認項目:**
- [ ] ページが正常に表示される
- [ ] 既存の投稿が表示される
- [ ] エラーメッセージが表示されない
- [ ] フォームが表示される

### 2. 新規投稿テスト

**手順:**
1. 名前: `テストユーザー`
2. タイトル: `テスト投稿`
3. メッセージ: `BbsLogRepositoryのテストです`
4. 「投稿」ボタンをクリック

**確認項目:**
- [ ] 投稿完了画面が表示される
- [ ] エラーが出ない
- [ ] メインページに戻る
- [ ] 新しい投稿が一番上に表示される

### 3. ページリロードテスト

**手順:**
1. ブラウザーをリロード (F5)
2. 複数回リロード

**確認項目:**
- [ ] 投稿が正しく表示される
- [ ] 投稿の順序が正しい
- [ ] エラーが出ない

### 4. 複数投稿テスト

**手順:**
1. 3〜5件の投稿を連続で行う
2. 各投稿後にメインページで確認

**確認項目:**
- [ ] 全ての投稿が表示される
- [ ] 新しい投稿が上に表示される
- [ ] 投稿番号が連番になっている

## デバッグ方法

### ログ確認

**Dockerログ:**
```bash
docker-compose logs -f web
```

**PHPエラーログ:**
```bash
docker-compose exec web tail -f /var/log/apache2/error.log
```

**Repository使用確認:**
```bash
docker-compose logs web | grep "loadmessage:"
```

以下のログが表示されるはず:
- `loadmessage: Using BbsLogRepository` ← Repository使用中
- `loadmessage: Fallback to file operations` ← フォールバック

### ログファイル確認

**メインログ:**
```bash
cat storage/app/bbs.log
```

**ログ件数:**
```bash
wc -l storage/app/bbs.log
```

**最新の投稿:**
```bash
head -n 1 storage/app/bbs.log
```

## トラブルシューティング

### エラー: "Failed to open log file"

**原因:** ファイルパーミッション

**解決:**
```bash
chmod -R 777 storage/app
```

### エラー: "Failed to read log file"

**原因:** ログファイルが存在しない

**解決:**
```bash
touch storage/app/bbs.log
chmod 666 storage/app/bbs.log
```

### 投稿が表示されない

**確認:**
```bash
# ログファイルの内容確認
cat storage/app/bbs.log

# Dockerログ確認
docker-compose logs web | tail -50
```

### Repository が使われていない

**確認:**
```bash
# ログで確認
docker-compose logs web | grep "loadmessage:"
```

**"Fallback to file operations"が表示される場合:**
- DIコンテナの設定を確認
- routes.phpでリポジトリが注入されているか確認

## 期待される動作

### 正常時

1. **メインページ表示:**
   - ログ: `loadmessage: Using BbsLogRepository`
   - 投稿一覧が表示される

2. **新規投稿:**
   - 投稿完了画面が表示される
   - メインページに戻ると新しい投稿が表示される

3. **リロード:**
   - 投稿が保持されている
   - 順序が正しい

### Repository使用確認

**期待されるログ:**
```
loadmessage: Using BbsLogRepository
```

このログが表示されれば、BbsLogRepositoryが正しく使用されています。

## テスト完了チェックリスト

- [ ] メインページが表示される
- [ ] 新規投稿ができる
- [ ] 投稿が保存される
- [ ] リロード後も投稿が表示される
- [ ] 複数投稿が正しく動作する
- [ ] エラーログにエラーがない
- [ ] "Using BbsLogRepository"ログが表示される

## デバッグログの削除

テスト完了後、デバッグログを削除:

`src/Kuzuha/Webapp.php`の`loadmessage()`から以下を削除:
```php
error_log("loadmessage: Using BbsLogRepository");
error_log("loadmessage: Fallback to file operations");
error_log("loadmessage: Using file operations for archive log");
```
