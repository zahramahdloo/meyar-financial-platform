<?php
/** MEYAR — مدیریت کاربران و نقش‌ها */
require_once __DIR__ . '/_panel.php';
panel_guard('users');

$pdo  = meyar_db();
$me   = meyar_user();
$msg = ''; $err = '';
$allPerms = meyar_all_perms();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!meyar_csrf_ok()) {
        $err = 'توکن امنیتی نامعتبر.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_user') {
            $uid      = (int)($_POST['user_id'] ?? 0);
            $username = trim((string)($_POST['username'] ?? ''));
            $display  = trim((string)($_POST['display_name'] ?? ''));
            $roleId   = (int)($_POST['role_id'] ?? 0);
            $pass     = (string)($_POST['password'] ?? '');
            $active   = !empty($_POST['active']) ? 1 : 0;

            if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
                $err = 'نام کاربری: ۳ تا ۳۰ کاراکتر انگلیسی/عدد.';
            } elseif (!$roleId) {
                $err = 'نقش را انتخاب کنید.';
            } elseif ($uid === 0 && strlen($pass) < 8) {
                $err = 'برای کاربر جدید رمز حداقل ۸ کاراکتری لازم است.';
            } else {
                try {
                    if ($uid) {
                        if ($uid === $me['id'] && !$active) {
                            $err = 'نمی‌توانید حساب خودتان را غیرفعال کنید.';
                        } else {
                            $pdo->prepare("UPDATE users SET username=?, display_name=?, role_id=?, active=? WHERE id=?")
                                ->execute([$username, $display, $roleId, $active, $uid]);
                            if (strlen($pass) >= 8) {
                                $pdo->prepare("UPDATE users SET pass_hash=? WHERE id=?")
                                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                            }
                            $msg = 'کاربر به‌روزرسانی شد ✔';
                        }
                    } else {
                        $pdo->prepare("INSERT INTO users(username, pass_hash, display_name, role_id, active, created_at) VALUES(?,?,?,?,?,?)")
                            ->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $display, $roleId, $active, time()]);
                        $msg = 'کاربر جدید ساخته شد ✔';
                    }
                } catch (PDOException $e) {
                    $err = 'نام کاربری تکراری است.';
                }
            }
        }

        if ($action === 'delete_user') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid === $me['id']) {
                $err = 'نمی‌توانید خودتان را حذف کنید.';
            } else {
                $u = $pdo->prepare("SELECT username FROM users WHERE id=?");
                $u->execute([$uid]);
                $row = $u->fetch();
                if ($row && $row['username'] === 'gnz') {
                    $err = 'مدیر اصلی (gnz) قابل حذف نیست.';
                } else {
                    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
                    $msg = 'کاربر حذف شد.';
                }
            }
        }

        if ($action === 'save_role') {
            $rid   = (int)($_POST['role_id'] ?? 0);
            $name  = trim((string)($_POST['role_name'] ?? ''));
            $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), array_merge(array_keys($allPerms), ['*'])));
            if ($name === '') {
                $err = 'نام نقش الزامی است.';
            } elseif (!$perms) {
                $err = 'حداقل یک مجوز انتخاب کنید.';
            } else {
                try {
                    if ($rid) {
                        $pdo->prepare("UPDATE roles SET name=?, perms=? WHERE id=?")
                            ->execute([$name, json_encode($perms, JSON_UNESCAPED_UNICODE), $rid]);
                        $msg = 'نقش به‌روزرسانی شد ✔';
                    } else {
                        $pdo->prepare("INSERT INTO roles(name, perms) VALUES(?,?)")
                            ->execute([$name, json_encode($perms, JSON_UNESCAPED_UNICODE)]);
                        $msg = 'نقش جدید ساخته شد ✔';
                    }
                } catch (PDOException $e) {
                    $err = 'نام نقش تکراری است.';
                }
            }
        }

        if ($action === 'delete_role') {
            $rid = (int)($_POST['role_id'] ?? 0);
            $c = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id=?");
            $c->execute([$rid]);
            if ((int)$c->fetchColumn() > 0) {
                $err = 'این نقش به کاربرانی اختصاص دارد؛ اول نقش آن‌ها را عوض کنید.';
            } else {
                $pdo->prepare("DELETE FROM roles WHERE id=?")->execute([$rid]);
                $msg = 'نقش حذف شد.';
            }
        }
    }
}

$users = $pdo->query("SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$csrf  = meyar_csrf();

$editUser = null;
if (isset($_GET['edit_user'])) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([(int)$_GET['edit_user']]);
    $editUser = $st->fetch() ?: null;
}
$editRole = null;
if (isset($_GET['edit_role'])) {
    $st = $pdo->prepare("SELECT * FROM roles WHERE id=?");
    $st->execute([(int)$_GET['edit_role']]);
    $editRole = $st->fetch() ?: null;
}

panel_header('کاربران و نقش‌ها', 'users');
if ($msg) echo '<div class="msg">' . meyar_h($msg) . '</div>';
if ($err) echo '<div class="err">' . meyar_h($err) . '</div>';
?>

<div class="grid2" style="align-items:start">
  <div>
    <div class="card">
      <h2><?= $editUser ? 'ویرایش کاربر: ' . meyar_h($editUser['username']) : 'افزودن کاربر جدید' ?></h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="save_user">
        <input type="hidden" name="user_id" value="<?= $editUser ? (int)$editUser['id'] : 0 ?>">
        <div class="grid2" style="margin-bottom:12px">
          <div><label>نام کاربری (انگلیسی)</label><input type="text" name="username" value="<?= meyar_h($editUser['username'] ?? '') ?>" required style="direction:ltr;text-align:left"></div>
          <div><label>نام نمایشی</label><input type="text" name="display_name" value="<?= meyar_h($editUser['display_name'] ?? '') ?>"></div>
          <div><label>نقش</label>
            <select name="role_id" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= $editUser && (int)$editUser['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= meyar_h($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>رمز عبور <?= $editUser ? '(خالی = بدون تغییر)' : '(حداقل ۸ کاراکتر)' ?></label><input type="password" name="password" autocomplete="new-password"></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
          <input type="checkbox" name="active" value="1" <?= !$editUser || $editUser['active'] ? 'checked' : '' ?> style="width:auto"> فعال
        </label>
        <button class="btn" type="submit">💾 ذخیره کاربر</button>
        <?php if ($editUser): ?><a class="btn btn-ghost" href="users.php">انصراف</a><?php endif; ?>
      </form>
    </div>

    <div class="card">
      <h2>کاربران</h2>
      <table>
        <thead><tr><th>کاربر</th><th>نقش</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><b><?= meyar_h($u['display_name'] ?: $u['username']) ?></b> <span class="hint" style="direction:ltr;display:inline-block">(<?= meyar_h($u['username']) ?>)</span></td>
            <td><?= meyar_h($u['role_name']) ?></td>
            <td><?= $u['active'] ? '<span class="pill ok">فعال</span>' : '<span class="pill warn">غیرفعال</span>' ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-sm btn-ghost" href="users.php?edit_user=<?= (int)$u['id'] ?>">✏️</a>
              <?php if ($u['username'] !== 'gnz' && (int)$u['id'] !== $me['id']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('حذف کاربر؟')">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <div class="card">
      <h2><?= $editRole ? 'ویرایش نقش: ' . meyar_h($editRole['name']) : 'افزودن نقش جدید' ?></h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="save_role">
        <input type="hidden" name="role_id" value="<?= $editRole ? (int)$editRole['id'] : 0 ?>">
        <div style="margin-bottom:12px"><label>نام نقش</label><input type="text" name="role_name" value="<?= meyar_h($editRole['name'] ?? '') ?>" required></div>
        <label>مجوزها:</label>
        <?php $rp = $editRole ? (json_decode($editRole['perms'], true) ?: []) : []; ?>
        <div style="display:flex;flex-direction:column;gap:6px;margin:8px 0 14px">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gold-l)">
            <input type="checkbox" name="perms[]" value="*" <?= in_array('*', $rp, true) ? 'checked' : '' ?> style="width:auto"> ⭐ دسترسی کامل (همه بخش‌ها)
          </label>
          <?php foreach ($allPerms as $p => $label): ?>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink)">
            <input type="checkbox" name="perms[]" value="<?= $p ?>" <?= in_array($p, $rp, true) ? 'checked' : '' ?> style="width:auto"> <?= meyar_h($label) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <button class="btn" type="submit">💾 ذخیره نقش</button>
        <?php if ($editRole): ?><a class="btn btn-ghost" href="users.php">انصراف</a><?php endif; ?>
      </form>
    </div>

    <div class="card">
      <h2>نقش‌ها</h2>
      <table>
        <thead><tr><th>نقش</th><th>مجوزها</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r):
            $rpv = json_decode($r['perms'], true) ?: []; ?>
          <tr>
            <td><b><?= meyar_h($r['name']) ?></b></td>
            <td class="hint"><?= in_array('*', $rpv, true) ? '⭐ کامل' : meyar_h(implode('، ', array_map(function ($p) use ($allPerms) { return $allPerms[$p] ?? $p; }, $rpv))) ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-sm btn-ghost" href="users.php?edit_role=<?= (int)$r['id'] ?>">✏️</a>
              <form method="post" style="display:inline" onsubmit="return confirm('حذف نقش؟')">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_role">
                <input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php panel_footer(); ?>
