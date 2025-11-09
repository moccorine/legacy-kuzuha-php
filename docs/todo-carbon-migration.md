# TODO: Carbon化（日付処理のモダン化）

## 概要

現在の `date()` 関数ベースの日付処理を Carbon ライブラリに移行し、可読性・保守性・国際化対応を向上させる。

## 現状

- **date() 使用箇所**: 15箇所
- **タイムゾーン**: 設定済み（APP_TIMEZONE）
- **DateHelper**: 基本的なヘルパーのみ

## 目標

- 全ての date() を Carbon に置き換え
- 日付計算を簡単に
- 多言語対応の日付表示
- 人間が読みやすい相対時間表示

---

## Phase 1: Carbon インストールと DateHelper 拡張

### タスク

- [ ] Carbon をインストール
  ```bash
  composer require nesbot/carbon
  ```

- [ ] DateHelper を Carbon ベースに拡張
  - [ ] `now()` メソッド追加
  - [ ] `fromTimestamp()` メソッド追加
  - [ ] `format()` メソッド追加
  - [ ] `getLogFilename()` メソッド追加
  - [ ] `diffForHumans()` メソッド追加（オプション）
  - [ ] タイムゾーン設定を環境変数から読み込み

- [ ] DateHelper のテスト作成
  - [ ] タイムゾーン設定テスト
  - [ ] フォーマットテスト
  - [ ] ログファイル名生成テスト
  - [ ] 日付計算テスト

### 実装例

```php
<?php

namespace App\Utils;

use Carbon\Carbon;

class DateHelper
{
    private static ?string $timezone = null;

    /**
     * Set timezone from environment
     */
    public static function setTimezone(?string $timezone = null): void
    {
        self::$timezone = $timezone ?? $_ENV['APP_TIMEZONE'] ?? 'Asia/Tokyo';
        Carbon::setLocale($_ENV['APP_LOCALE'] ?? 'ja');
    }

    /**
     * Get timezone
     */
    public static function getTimezone(): string
    {
        if (self::$timezone === null) {
            self::setTimezone();
        }
        return self::$timezone;
    }

    /**
     * Get current time
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::getTimezone());
    }

    /**
     * Create Carbon instance from timestamp
     */
    public static function fromTimestamp(int $timestamp): Carbon
    {
        return Carbon::createFromTimestamp($timestamp, self::getTimezone());
    }

    /**
     * Format timestamp
     */
    public static function format(int $timestamp, string $format): string
    {
        return self::fromTimestamp($timestamp)->format($format);
    }

    /**
     * Get date string with day of week
     * 
     * @deprecated Use format() instead
     */
    public static function getDateString(int $timestamp, string $format = ''): string
    {
        if (!$format) {
            $format = 'Y/m/d(-) H:i:s';
        }
        
        $carbon = self::fromTimestamp($timestamp);
        $datestr = $carbon->format($format);
        
        if (str_contains($format, '-')) {
            $dayOfWeek = $carbon->locale('en')->format('D'); // Sun, Mon, etc.
            $datestr = str_replace('-', $dayOfWeek, $datestr);
        }
        
        return $datestr;
    }

    /**
     * Get log filename (YYYYMM or YYYYMMDD)
     */
    public static function getLogFilename(int $timestamp, bool $daily = false): string
    {
        $format = $daily ? 'Ymd' : 'Ym';
        return self::format($timestamp, $format);
    }

    /**
     * Get human-readable time difference
     */
    public static function diffForHumans(int $timestamp): string
    {
        return self::fromTimestamp($timestamp)->diffForHumans();
    }

    /**
     * Add days to timestamp
     */
    public static function addDays(int $timestamp, int $days): int
    {
        return self::fromTimestamp($timestamp)->addDays($days)->timestamp;
    }

    /**
     * Subtract days from timestamp
     */
    public static function subDays(int $timestamp, int $days): int
    {
        return self::fromTimestamp($timestamp)->subDays($days)->timestamp;
    }

    /**
     * Calculate microtime difference
     * 
     * @deprecated Use PerformanceTimer instead
     */
    public static function microtimeDiff(string $a, string $b): float
    {
        [$a_dec, $a_sec] = explode(' ', $a);
        [$b_dec, $b_sec] = explode(' ', $b);
        return $b_sec - $a_sec + $b_dec - $a_dec;
    }
}
```

### 見積もり: 2-3時間

---

## Phase 2: ログファイル名生成の置き換え (8箇所)

### Bbsadmin.php (2箇所)

**現在:**
```php
// 行245
$oldlogfilename = date('Ym', $killntimes[$killid]) . ".$oldlogext";

// 行247
$oldlogfilename = date('Ymd', $killntimes[$killid]) . ".$oldlogext";
```

**置き換え後:**
```php
// 行245
$oldlogfilename = DateHelper::getLogFilename($killntimes[$killid], false) . ".$oldlogext";

// 行247
$oldlogfilename = DateHelper::getLogFilename($killntimes[$killid], true) . ".$oldlogext";
```

**タスク:**
- [ ] 行245を DateHelper::getLogFilename() に置き換え
- [ ] 行247を DateHelper::getLogFilename() に置き換え
- [ ] 動作確認

---

### Bbs.php (6箇所)

**現在:**
```php
// 行1450
$oldlogfilename = $dir . date('Ym', CURRENT_TIME) . ".$oldlogext";
$oldlogtitle = $this->config['BBSTITLE'] . date(' Y.m', CURRENT_TIME);

// 行1453
$oldlogfilename = $dir . date('Ymd', CURRENT_TIME) . ".$oldlogext";
$oldlogtitle = $this->config['BBSTITLE'] . date(' Y.m.d', CURRENT_TIME);

// 行1477
$limitdate = date('Ymd', $limitdate);

// 行1500
$currentfile = date('Ym', CURRENT_TIME) . '.html';

// 行1502
$currentfile = date('Ymd', CURRENT_TIME) . '.html';
```

**置き換え後:**
```php
// 行1450
$oldlogfilename = $dir . DateHelper::getLogFilename(CURRENT_TIME, false) . ".$oldlogext";
$oldlogtitle = $this->config['BBSTITLE'] . DateHelper::format(CURRENT_TIME, ' Y.m');

// 行1453
$oldlogfilename = $dir . DateHelper::getLogFilename(CURRENT_TIME, true) . ".$oldlogext";
$oldlogtitle = $this->config['BBSTITLE'] . DateHelper::format(CURRENT_TIME, ' Y.m.d');

// 行1477
$limitdate = DateHelper::getLogFilename($limitdate, true);

// 行1500
$currentfile = DateHelper::getLogFilename(CURRENT_TIME, false) . '.html';

// 行1502
$currentfile = DateHelper::getLogFilename(CURRENT_TIME, true) . '.html';
```

**タスク:**
- [ ] 行1450-1451を置き換え
- [ ] 行1453-1454を置き換え
- [ ] 行1477を置き換え
- [ ] 行1500を置き換え
- [ ] 行1502を置き換え
- [ ] 動作確認

### 見積もり: 1時間

---

## Phase 3: 日付表示の置き換え (5箇所)

### Getlog.php (5箇所)

**現在:**
```php
// 行179
$ftime = date('Y/m/d H:i:s', $fstat[9]);

// 行710
$message['NDATESTR'] = date('dH', $message['NDATE']);

// 行718
$message['NDATESTR'] = date('Hi', $message['NDATE']);

// 行850
$tt = date('m/d H:i:s', $ttime[$tid[$i]]);

// 行902
'FTIME' => date('Y/m/d H:i:s', $fstat[9]),
```

**置き換え後:**
```php
// 行179
$ftime = DateHelper::format($fstat[9], 'Y/m/d H:i:s');

// 行710
$message['NDATESTR'] = DateHelper::format($message['NDATE'], 'dH');

// 行718
$message['NDATESTR'] = DateHelper::format($message['NDATE'], 'Hi');

// 行850
$tt = DateHelper::format($ttime[$tid[$i]], 'm/d H:i:s');

// 行902
'FTIME' => DateHelper::format($fstat[9], 'Y/m/d H:i:s'),
```

**タスク:**
- [ ] 行179を置き換え
- [ ] 行710を置き換え
- [ ] 行718を置き換え
- [ ] 行850を置き換え
- [ ] 行902を置き換え
- [ ] 動作確認

### 見積もり: 30分

---

## Phase 4: ファイル名生成の置き換え (1箇所)

### Imagebbs.php (1箇所)

**現在:**
```php
// 行193
$filename = $this->config['UPLOADDIR'] . str_pad($fileid, 5, '0', STR_PAD_LEFT) 
    . '_' . date('YmdHis', CURRENT_TIME) . $fileext;
```

**置き換え後:**
```php
// 行193
$filename = $this->config['UPLOADDIR'] . str_pad($fileid, 5, '0', STR_PAD_LEFT) 
    . '_' . DateHelper::format(CURRENT_TIME, 'YmdHis') . $fileext;
```

**タスク:**
- [ ] 行193を置き換え
- [ ] 動作確認

### 見積もり: 10分

---

## Phase 5: 初期化処理の追加

### public/index.php

**追加:**
```php
// Set timezone from environment or default to JST
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Tokyo');

// Initialize DateHelper with timezone
\App\Utils\DateHelper::setTimezone($_ENV['APP_TIMEZONE'] ?? 'Asia/Tokyo');
```

**タスク:**
- [ ] index.php に DateHelper 初期化を追加
- [ ] 動作確認

### 見積もり: 10分

---

## Phase 6: テスト作成

### tests/Unit/Utils/DateHelperTest.php

**テストケース:**
- [ ] setTimezone sets timezone correctly
- [ ] now returns current time in correct timezone
- [ ] fromTimestamp creates Carbon instance
- [ ] format formats timestamp correctly
- [ ] getLogFilename generates monthly filename (YYYYMM)
- [ ] getLogFilename generates daily filename (YYYYMMDD)
- [ ] diffForHumans returns human-readable time
- [ ] addDays adds days correctly
- [ ] subDays subtracts days correctly
- [ ] getDateString maintains backward compatibility

**実装例:**
```php
<?php

use App\Utils\DateHelper;

beforeEach(function () {
    DateHelper::setTimezone('Asia/Tokyo');
});

test('now returns current time in correct timezone', function () {
    $now = DateHelper::now();
    
    expect($now->timezone->getName())->toBe('Asia/Tokyo');
});

test('fromTimestamp creates Carbon instance', function () {
    $timestamp = 1699876860; // 2023-11-13 12:34:20 JST
    $carbon = DateHelper::fromTimestamp($timestamp);
    
    expect($carbon->timestamp)->toBe($timestamp);
    expect($carbon->timezone->getName())->toBe('Asia/Tokyo');
});

test('format formats timestamp correctly', function () {
    $timestamp = 1699876860; // 2023-11-13 12:34:20 JST
    $formatted = DateHelper::format($timestamp, 'Y-m-d H:i:s');
    
    expect($formatted)->toBe('2023-11-13 12:34:20');
});

test('getLogFilename generates monthly filename', function () {
    $timestamp = 1699876860; // 2023-11-13
    $filename = DateHelper::getLogFilename($timestamp, false);
    
    expect($filename)->toBe('202311');
});

test('getLogFilename generates daily filename', function () {
    $timestamp = 1699876860; // 2023-11-13
    $filename = DateHelper::getLogFilename($timestamp, true);
    
    expect($filename)->toBe('20231113');
});

test('diffForHumans returns human-readable time', function () {
    $timestamp = time() - 3600; // 1 hour ago
    $diff = DateHelper::diffForHumans($timestamp);
    
    expect($diff)->toContain('1時間前'); // Japanese locale
});

test('addDays adds days correctly', function () {
    $timestamp = 1699876860; // 2023-11-13
    $newTimestamp = DateHelper::addDays($timestamp, 7);
    
    $expected = DateHelper::fromTimestamp($timestamp)->addDays(7)->timestamp;
    expect($newTimestamp)->toBe($expected);
});

test('subDays subtracts days correctly', function () {
    $timestamp = 1699876860; // 2023-11-13
    $newTimestamp = DateHelper::subDays($timestamp, 7);
    
    $expected = DateHelper::fromTimestamp($timestamp)->subDays(7)->timestamp;
    expect($newTimestamp)->toBe($expected);
});
```

### 見積もり: 1時間

---

## Phase 7: 統合テストと動作確認

### テスト項目

- [ ] 投稿機能が正常に動作する
- [ ] ログファイルが正しい名前で生成される
- [ ] 日付表示が正しい
- [ ] 画像アップロードのファイル名が正しい
- [ ] 検索機能が正常に動作する
- [ ] ログアーカイブが正しく動作する
- [ ] タイムゾーン変更が反映される

### 見積もり: 1時間

---

## 総見積もり

| Phase | タスク | 見積もり |
|-------|--------|----------|
| Phase 1 | Carbon インストールと DateHelper 拡張 | 2-3時間 |
| Phase 2 | ログファイル名生成の置き換え (8箇所) | 1時間 |
| Phase 3 | 日付表示の置き換え (5箇所) | 30分 |
| Phase 4 | ファイル名生成の置き換え (1箇所) | 10分 |
| Phase 5 | 初期化処理の追加 | 10分 |
| Phase 6 | テスト作成 | 1時間 |
| Phase 7 | 統合テストと動作確認 | 1時間 |
| **合計** | | **6-7時間** |

---

## メリット

### 1. 可読性向上
```php
// Before
$oldlogfilename = date('Ym', CURRENT_TIME) . '.dat';

// After
$oldlogfilename = DateHelper::getLogFilename(CURRENT_TIME, false) . '.dat';
```

### 2. 日付計算が簡単
```php
// Before
$limitdate = strtotime('-30 days', CURRENT_TIME);
$limitdate = date('Ymd', $limitdate);

// After
$limitdate = DateHelper::getLogFilename(
    DateHelper::subDays(CURRENT_TIME, 30), 
    true
);
```

### 3. 多言語対応
```php
// Japanese
DateHelper::setTimezone('Asia/Tokyo');
Carbon::setLocale('ja');
echo DateHelper::diffForHumans($timestamp); // "3時間前"

// English
Carbon::setLocale('en');
echo DateHelper::diffForHumans($timestamp); // "3 hours ago"
```

### 4. タイムゾーン対応
```php
// JST
DateHelper::setTimezone('Asia/Tokyo');
echo DateHelper::format(CURRENT_TIME, 'Y-m-d H:i:s'); // 2025-11-09 15:00:00

// UTC
DateHelper::setTimezone('UTC');
echo DateHelper::format(CURRENT_TIME, 'Y-m-d H:i:s'); // 2025-11-09 06:00:00
```

---

## 注意事項

### 後方互換性

- `getDateString()` メソッドは維持（@deprecated）
- `microtimeDiff()` メソッドは維持（@deprecated）
- 既存の date() 呼び出しは段階的に置き換え

### テスト

- 全ての置き換え後に動作確認
- ログファイル名の形式が変わらないことを確認
- 日付表示が正しいことを確認

### ロールバック

- 各 Phase ごとにコミット
- 問題があれば即座にロールバック可能

---

## 参考資料

- [Carbon Documentation](https://carbon.nesbot.com/docs/)
- [Carbon Localization](https://carbon.nesbot.com/docs/#api-localization)
- [PHP DateTime](https://www.php.net/manual/en/class.datetime.php)
- [docs/date-handling-analysis.md](./date-handling-analysis.md) - 現状分析

---

## 関連ファイル

- `src/Utils/DateHelper.php` - 拡張対象
- `src/Kuzuha/Bbs.php` - 6箇所置き換え
- `src/Kuzuha/Getlog.php` - 5箇所置き換え
- `src/Kuzuha/Bbsadmin.php` - 2箇所置き換え
- `src/Kuzuha/Imagebbs.php` - 1箇所置き換え
- `public/index.php` - 初期化追加
- `tests/Unit/Utils/DateHelperTest.php` - 新規作成
