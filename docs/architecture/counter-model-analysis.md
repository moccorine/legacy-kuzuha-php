# カウンターのモデル化分析

## 現状: 部分的にモデル化されている

### モデル化されているもの ✅

**AccessCounter（アクセスカウンター）**
```
Interface: AccessCounterRepositoryInterface
├── AccessCounterCsvRepository (CSV実装)
└── AccessCounterSqliteRepository (SQLite実装)

メソッド:
- increment(): int - カウンターをインクリメントして新しい値を返す
- getCurrent(): int - 現在の値を取得（インクリメントなし）
- getCountLevel(): int|false - カウンターレベル取得
```

**ParticipantCounter（参加者カウンター）**
```
Interface: ParticipantCounterRepositoryInterface
├── ParticipantCounterCsvRepository (CSV実装)
└── ParticipantCounterSqliteRepository (SQLite実装)

メソッド:
- recordVisit(string $userKey, int $timestamp, int $timeoutSeconds): int
  訪問を記録してアクティブ参加者数を返す
- getActiveCount(int $currentTime, int $timeoutSeconds): int
  アクティブ参加者数を取得（記録なし）
```

**使用方法:**
```php
// Bbs.php - コンストラクタでDI
public function __construct(
    ?AccessCounterRepositoryInterface $accessCounterRepo = null,
    ?ParticipantCounterRepositoryInterface $participantCounterRepo = null
) {
    parent::__construct();
    $this->accessCounterRepo = $accessCounterRepo;
    $this->participantCounterRepo = $participantCounterRepo;
}

// getStatsData() - 統計情報取得
if ($this->config['SHOW_COUNTER'] && $this->accessCounterRepo !== null) {
    $counter = number_format($this->accessCounterRepo->increment());
}

if ($this->config['CNTFILENAME'] && $this->participantCounterRepo !== null) {
    $mbrcount = number_format(
        $this->participantCounterRepo->recordVisit($userKey, CURRENT_TIME, $this->config['CNTLIMIT'])
    );
}
```

**評価:** ✅ 良好な実装
- インターフェースで抽象化
- CSV/SQLite両対応
- DIコンテナで注入
- ユニットテスト完備
- ドキュメント完備（`docs/counter-*.md`）

## モデル化されていないもの ❌

### 1. ログファイル操作（bbs.log）

**現状:**
```php
// Bbs.php - 直接ファイル操作
$fh = @fopen($this->config['LOGFILENAME'], 'rb+');
// ...
fwrite($fh, $logdata);
```

**問題点:**
- ファイル操作がビジネスロジックに混在
- エラーハンドリングが不十分（`@`で抑制）
- テストが困難
- 複数箇所で重複したコード

**あるべき姿:**
```php
Interface: LogRepositoryInterface
├── LogFileRepository (ファイル実装)
└── LogDatabaseRepository (DB実装 - 将来)

メソッド:
- append(Message $message): void
- getAll(): array
- getRange(int $start, int $end): array
- search(array $criteria): array
- delete(int $postId): bool
```

### 2. アーカイブログ操作（archives/*.dat）

**現状:**
```php
// Bbs.php - 直接ファイル操作
$fh = @fopen($oldlogfilename, 'ab');
fwrite($fh, $msgdata);
```

**問題点:**
- ログファイルと同様の問題
- アーカイブロジックが分散

**あるべき姿:**
```php
Interface: ArchiveRepositoryInterface
├── ArchiveFileRepository (ファイル実装)
└── ArchiveDatabaseRepository (DB実装 - 将来)

メソッド:
- archive(Message $message, string $archiveKey): void
- getArchive(string $archiveKey): array
- listArchives(): array
```

### 3. セッション/Cookie管理

**現状:**
```php
// Webapp.php - 直接$_COOKIE, $_SESSION操作
$this->session['U'] = $_COOKIE['u'] ?? '';
$this->session['I'] = $_COOKIE['i'] ?? '';
```

**問題点:**
- グローバル変数への直接アクセス
- テストが困難
- セキュリティ設定が分散

**あるべき姿:**
```php
Interface: SessionRepositoryInterface
├── CookieSessionRepository
└── DatabaseSessionRepository (将来)

メソッド:
- get(string $key): mixed
- set(string $key, mixed $value): void
- has(string $key): bool
- delete(string $key): void
```

## ファイル操作の現状

```
storage/app/
├── bbs.log              # メインログ（直接操作）
├── bbs.cnt              # アクセスカウンター（モデル化済み）
├── archives/            # アーカイブログ（直接操作）
│   ├── 20251108.dat
│   └── 20251109.dat
└── count/               # 参加者カウンター（モデル化済み）
    ├── count0.dat
    ├── count1.dat
    └── ...
```

## 推奨される改善

### 優先度1: LogRepository の作成

**理由:**
- 最も頻繁に使用される
- ビジネスロジックに深く関わる
- テストが必要

**実装:**
```php
namespace App\Models\Repositories;

interface LogRepositoryInterface
{
    public function append(array $message): void;
    public function getAll(): array;
    public function getRange(int $start, int $end): array;
    public function findById(int $postId): ?array;
    public function deleteById(int $postId): bool;
}

class LogFileRepository implements LogRepositoryInterface
{
    public function __construct(private string $logFilePath) {}
    
    public function append(array $message): void
    {
        $fh = fopen($this->logFilePath, 'ab');
        if (!$fh) {
            throw new \RuntimeException("Failed to open log file");
        }
        fwrite($fh, implode(',', $message) . "\n");
        fclose($fh);
    }
    
    // ...
}
```

### 優先度2: ArchiveRepository の作成

**理由:**
- LogRepositoryと密接に関連
- アーカイブロジックの整理

### 優先度3: SessionRepository の作成

**理由:**
- セキュリティ向上
- テスタビリティ向上

## カウンターモデル化の評価

### 成功している点 ✅

1. **インターフェース設計**
   - 明確な責務分離
   - 実装の切り替えが容易

2. **DIパターン**
   - コンストラクタインジェクション
   - DIコンテナで管理

3. **テスト**
   - ユニットテスト完備
   - モックが容易

4. **ドキュメント**
   - 仕様書完備
   - 実装計画書完備

### 他のデータ操作に適用すべき点

1. **同じパターンを踏襲**
   - Interface + 複数実装
   - DIコンテナで注入
   - ユニットテスト作成

2. **段階的移行**
   - 既存コードを壊さない
   - 新機能から適用
   - 徐々に置き換え

## 次のステップ

1. **LogRepositoryInterface 作成**
   - インターフェース定義
   - LogFileRepository実装
   - ユニットテスト作成

2. **Bbs.php のリファクタリング**
   - LogRepositoryを注入
   - 直接ファイル操作を置き換え

3. **ArchiveRepositoryInterface 作成**
   - LogRepositoryと同様のパターン

4. **SessionRepositoryInterface 作成**
   - セッション管理の抽象化
