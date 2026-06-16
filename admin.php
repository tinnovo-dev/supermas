<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock — Supermas Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/main.css">
</head>
<body style="background:#f5f8ff;">
<?php
session_start();

$PASSWORD = '1234';

// Login
if (isset($_POST['pwd'])) {
  if ($_POST['pwd'] === $PASSWORD) {
    $_SESSION['supermas_admin'] = true;
  } else {
    $error_login = true;
  }
}

// Logout
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: admin.php');
  exit;
}

// Connexió BD
try {
  $db = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=manager;charset=utf8mb4',
    'manager',
    'M4n4g3r_T1nn0v0!',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (Exception $e) {
  die('<div class="alert alert-danger m-4">Error de connexió a la BD.</div>');
}

// ── ACCIONS ──────────────────────────────────────────────

// Afegir categoria
if (isset($_SESSION['supermas_admin']) && $_POST['accio'] === 'afegir_categoria') {
  $nom_cat = trim($_POST['nom_cat']);
  if ($nom_cat) {
    $stmt = $db->prepare('INSERT INTO catalogue_category (catalogue, catalogue_category, name, date_create, date_modificated) VALUES (1, -1, ?, NOW(), NOW())');
    $stmt->execute([$nom_cat]);
    $ok_msg = "Categoria «{$nom_cat}» afegida.";
  }
}

// Eliminar categoria (i tots els seus productes)
if (isset($_SESSION['supermas_admin']) && $_POST['accio'] === 'eliminar_categoria') {
  $id_cat = (int)$_POST['id_cat'];
  // Obtenir tots els models de la categoria
  $stmt = $db->prepare('SELECT m.id FROM catalogue_item_model m INNER JOIN catalogue_item_model_category imc ON m.catalogue_item_model_category = imc.id WHERE imc.catalogue_category = ?');
  $stmt->execute([$id_cat]);
  $models = $stmt->fetchAll(PDO::FETCH_COLUMN);
  foreach ($models as $id_model) {
    $db->prepare('DELETE FROM catalogue_field_value WHERE object = ?')->execute([$id_model]);
    $db->prepare('DELETE FROM catalogue_item WHERE catalogue_item_model = ?')->execute([$id_model]);
    $db->prepare('DELETE FROM catalogue_item_model WHERE id = ?')->execute([$id_model]);
  }
  $db->prepare('DELETE FROM catalogue_item_model_category WHERE catalogue_category = ?')->execute([$id_cat]);
  $db->prepare('DELETE FROM catalogue_category WHERE id = ?')->execute([$id_cat]);
  $ok_msg = "Categoria eliminada" . (!empty($models) ? " (i " . count($models) . " productes)." : ".");
}

// Afegir producte
if (isset($_SESSION['supermas_admin']) && $_POST['accio'] === 'afegir') {
  $nom    = trim($_POST['nom']);
  $cat    = (int)$_POST['categoria'];
  $preu   = trim($_POST['preu']);
  $stock  = trim($_POST['stock']);
  $unitat = trim($_POST['unitat']);

  if ($nom && $cat) {
    // Buscar o crear catalogue_item_model_category per aquesta categoria
    $stmt = $db->prepare('SELECT id FROM catalogue_item_model_category WHERE catalogue_category = ? AND catalogue = 1 LIMIT 1');
    $stmt->execute([$cat]);
    $imc = $stmt->fetchColumn();
    if (!$imc) {
      $stmt = $db->prepare('INSERT INTO catalogue_item_model_category (catalogue, catalogue_category, catalogue_item_model_category, name, date_create, date_modificated) VALUES (1, ?, -1, ?, NOW(), NOW())');
      $stmt->execute([$cat, $_POST['nom_categoria']]);
      $imc = $db->lastInsertId();
    }

    // Inserir model
    $stmt = $db->prepare('INSERT INTO catalogue_item_model (catalogue_item_model_category, name, date_create, date_modificated) VALUES (?, ?, NOW(), NOW())');
    $stmt->execute([$imc, $nom]);
    $id_model = $db->lastInsertId();

    // Gestionar imatge (redimensiona automàticament si supera 1 MB)
    $nom_imatge = '';
    if (!empty($_FILES['imatge']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['imatge']['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png','webp','gif'];
      if (!in_array($ext, $allowed)) {
        $error_img = "Format no permès. Usa JPG, PNG o WEBP.";
      } else {
        $nom_imatge = 'prod_' . $id_model . '.jpg';
        $dest = __DIR__ . '/assets/img/productes/' . $nom_imatge;
        $max_bytes = 1 * 1024 * 1024; // 1 MB

        // Carreguem la imatge original amb GD
        $info = getimagesize($_FILES['imatge']['tmp_name']);
        $src_w = $info[0]; $src_h = $info[1];
        $mime  = $info['mime'];
        $src = match($mime) {
          'image/jpeg' => imagecreatefromjpeg($_FILES['imatge']['tmp_name']),
          'image/png'  => imagecreatefrompng($_FILES['imatge']['tmp_name']),
          'image/webp' => imagecreatefromwebp($_FILES['imatge']['tmp_name']),
          'image/gif'  => imagecreatefromgif($_FILES['imatge']['tmp_name']),
          default      => false
        };

        if ($src) {
          // Redimensionem si cal (màx 1200px d'ample)
          $max_w = 1200;
          if ($src_w > $max_w) {
            $ratio  = $max_w / $src_w;
            $new_w  = $max_w;
            $new_h  = (int)($src_h * $ratio);
            $dst = imagecreatetruecolor($new_w, $new_h);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
            imagedestroy($src);
            $src = $dst;
          }

          // Ajustem la qualitat JPEG fins que quedi sota 1 MB
          $quality = 85;
          do {
            ob_start();
            imagejpeg($src, null, $quality);
            $data = ob_get_clean();
            $quality -= 5;
          } while (strlen($data) > $max_bytes && $quality > 20);

          file_put_contents($dest, $data);
          imagedestroy($src);
        } else {
          $nom_imatge = '';
          $error_img  = "No s'ha pogut processar la imatge.";
        }
      }
    }

    // Inserir camps dinàmics
    foreach (['preu' => $preu, 'stock' => $stock, 'unitat' => $unitat, 'imatge' => $nom_imatge] as $camp => $val) {
      $stmt = $db->prepare('SELECT id FROM catalogue_field WHERE name = ?');
      $stmt->execute([$camp]);
      $id_field = $stmt->fetchColumn();
      if ($id_field && $val !== '') {
        $stmt = $db->prepare('INSERT INTO catalogue_field_value (field, object, value, lang) VALUES (?, ?, ?, "ALL")');
        $stmt->execute([$id_field, $id_model, $val]);
      }
    }
    $ok_msg = "Producte «{$nom}» afegit correctament.";
  }
}

// Editar producte
if (isset($_SESSION['supermas_admin']) && $_POST['accio'] === 'editar') {
  $id_model = (int)$_POST['id_model'];
  $nom      = trim($_POST['nom']);
  $cat      = (int)$_POST['categoria'];
  $preu     = trim($_POST['preu']);
  $stock    = trim($_POST['stock']);
  $unitat   = trim($_POST['unitat']);

  if ($nom && $id_model) {
    // Actualitzar nom
    $db->prepare('UPDATE catalogue_item_model SET name = ?, date_modificated = NOW() WHERE id = ?')->execute([$nom, $id_model]);

    // Actualitzar categoria si cal
    $stmt = $db->prepare('SELECT id FROM catalogue_item_model_category WHERE catalogue_category = ? AND catalogue = 1 LIMIT 1');
    $stmt->execute([$cat]);
    $imc = $stmt->fetchColumn();
    if (!$imc) {
      $stmt = $db->prepare('INSERT INTO catalogue_item_model_category (catalogue, catalogue_category, catalogue_item_model_category, name, date_create, date_modificated) VALUES (1, ?, -1, ?, NOW(), NOW())');
      $stmt->execute([$cat, $_POST['nom_categoria']]);
      $imc = $db->lastInsertId();
    }
    $db->prepare('UPDATE catalogue_item_model SET catalogue_item_model_category = ? WHERE id = ?')->execute([$imc, $id_model]);

    // Gestionar imatge nova si s'ha pujat
    $nom_imatge = null;
    if (!empty($_FILES['imatge_edit']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['imatge_edit']['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png','webp','gif'];
      if (in_array($ext, $allowed)) {
        $nom_imatge = 'prod_' . $id_model . '.jpg';
        $dest = __DIR__ . '/assets/img/productes/' . $nom_imatge;
        $max_bytes = 1 * 1024 * 1024;
        $info = getimagesize($_FILES['imatge_edit']['tmp_name']);
        $src_w = $info[0]; $src_h = $info[1]; $mime = $info['mime'];
        $src = match($mime) {
          'image/jpeg' => imagecreatefromjpeg($_FILES['imatge_edit']['tmp_name']),
          'image/png'  => imagecreatefrompng($_FILES['imatge_edit']['tmp_name']),
          'image/webp' => imagecreatefromwebp($_FILES['imatge_edit']['tmp_name']),
          'image/gif'  => imagecreatefromgif($_FILES['imatge_edit']['tmp_name']),
          default      => false
        };
        if ($src) {
          if ($src_w > 1200) {
            $ratio = 1200 / $src_w;
            $dst = imagecreatetruecolor(1200, (int)($src_h * $ratio));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, 1200, (int)($src_h * $ratio), $src_w, $src_h);
            imagedestroy($src); $src = $dst;
          }
          $quality = 85;
          do { ob_start(); imagejpeg($src, null, $quality); $data = ob_get_clean(); $quality -= 5; }
          while (strlen($data) > $max_bytes && $quality > 20);
          file_put_contents($dest, $data);
          imagedestroy($src);
        }
      }
    }

    // Actualitzar camps dinàmics
    foreach (['preu' => $preu, 'stock' => $stock, 'unitat' => $unitat] as $camp => $val) {
      $stmt = $db->prepare('SELECT id FROM catalogue_field WHERE name = ?');
      $stmt->execute([$camp]);
      $id_field = $stmt->fetchColumn();
      if (!$id_field) continue;
      $stmt = $db->prepare('SELECT id FROM catalogue_field_value WHERE field = ? AND object = ?');
      $stmt->execute([$id_field, $id_model]);
      $id_fv = $stmt->fetchColumn();
      if ($id_fv) {
        $db->prepare('UPDATE catalogue_field_value SET value = ? WHERE id = ?')->execute([$val, $id_fv]);
      } elseif ($val !== '') {
        $db->prepare('INSERT INTO catalogue_field_value (field, object, value, lang) VALUES (?, ?, ?, "ALL")')->execute([$id_field, $id_model, $val]);
      }
    }

    // Actualitzar imatge si s'ha pujat una de nova
    if ($nom_imatge !== null) {
      $stmt = $db->prepare('SELECT id FROM catalogue_field WHERE name = "imatge"');
      $stmt->execute(); $id_field = $stmt->fetchColumn();
      $stmt = $db->prepare('SELECT id FROM catalogue_field_value WHERE field = ? AND object = ?');
      $stmt->execute([$id_field, $id_model]); $id_fv = $stmt->fetchColumn();
      if ($id_fv) $db->prepare('UPDATE catalogue_field_value SET value = ? WHERE id = ?')->execute([$nom_imatge, $id_fv]);
      else $db->prepare('INSERT INTO catalogue_field_value (field, object, value, lang) VALUES (?, ?, ?, "ALL")')->execute([$id_field, $id_model, $nom_imatge]);
    }

    $ok_msg = "Producte «{$nom}» actualitzat.";
  }
}

// Actualitzar stock
if (isset($_SESSION['supermas_admin']) && $_POST['accio'] === 'stock') {
  $id_model = (int)$_POST['id_model'];
  $nou_stock = trim($_POST['nou_stock']);
  $stmt = $db->prepare('SELECT id FROM catalogue_field WHERE name = "stock"');
  $stmt->execute();
  $id_field = $stmt->fetchColumn();
  $stmt = $db->prepare('SELECT id FROM catalogue_field_value WHERE field = ? AND object = ?');
  $stmt->execute([$id_field, $id_model]);
  $id_fv = $stmt->fetchColumn();
  if ($id_fv) {
    $db->prepare('UPDATE catalogue_field_value SET value = ? WHERE id = ?')->execute([$nou_stock, $id_fv]);
  } else {
    $db->prepare('INSERT INTO catalogue_field_value (field, object, value, lang) VALUES (?, ?, ?, "ALL")')->execute([$id_field, $id_model, $nou_stock]);
  }
  $ok_msg = "Stock actualitzat.";
}

// Eliminar producte
if (isset($_SESSION['supermas_admin']) && $_POST['accio'] === 'eliminar') {
  $id_model = (int)$_POST['id_model'];
  $db->prepare('DELETE FROM catalogue_field_value WHERE object = ?')->execute([$id_model]);
  $db->prepare('DELETE FROM catalogue_item WHERE catalogue_item_model = ?')->execute([$id_model]);
  $db->prepare('DELETE FROM catalogue_item_model WHERE id = ?')->execute([$id_model]);
  $ok_msg = "Producte eliminat.";
}

// ── LOGIN FORM ────────────────────────────────────────────
if (!isset($_SESSION['supermas_admin'])):
?>
  <div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card shadow" style="width:320px;border:none;border-radius:1rem;">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <img src="assets/img/supermas-logo.png" alt="Supermas" style="height:36px;">
        </div>
        <h6 class="text-center mb-4" style="color:var(--color-primary);font-weight:700;">Gestió d'Estoc</h6>
        <?php if (!empty($error_login)): ?>
          <div class="alert alert-danger py-2 text-center" style="font-size:.9rem;">Contrasenya incorrecta</div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="accio" value="">
          <div class="mb-3">
            <label class="form-label">Contrasenya</label>
            <input type="password" class="form-control" name="pwd" autofocus>
          </div>
          <button type="submit" class="btn-comanda w-100" style="border-radius:.75rem;">Entrar</button>
        </form>
      </div>
    </div>
  </div>
<?php else: ?>

  <!-- ── PANEL ADMIN ── -->
  <nav class="navbar navbar-supermas" style="border-bottom:3px solid var(--color-primary);">
    <div class="container d-flex justify-content-between align-items-center">
      <a href="index.html"><img src="assets/img/supermas-logo.png" alt="Supermas" style="height:36px;"></a>
      <span style="font-weight:700;color:var(--color-primary);">Gestió d'Estoc</span>
      <a href="?logout=1" class="btn btn-outline-secondary btn-sm rounded-pill">Sortir</a>
    </div>
  </nav>

  <div class="container py-4">

    <?php if (!empty($ok_msg)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($ok_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_img)): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($error_img) ?></div>
    <?php endif; ?>

    <div class="row g-4">

      <!-- Llista de productes -->
      <div class="col-lg-8">
        <div class="targeta">
          <h5 style="color:var(--color-primary);font-weight:700;margin-bottom:1.5rem;">Productes</h5>
          <?php
          $stmt = $db->prepare('
            SELECT m.id, m.name, cc.id AS cat_id, cc.name AS categoria,
              MAX(CASE WHEN f.name = "preu"   THEN fv.value END) AS preu,
              MAX(CASE WHEN f.name = "stock"  THEN fv.value END) AS stock,
              MAX(CASE WHEN f.name = "unitat" THEN fv.value END) AS unitat,
              MAX(CASE WHEN f.name = "imatge" THEN fv.value END) AS imatge
            FROM catalogue_item_model m
            INNER JOIN catalogue_item_model_category imc ON m.catalogue_item_model_category = imc.id
            INNER JOIN catalogue_category cc ON imc.catalogue_category = cc.id
            LEFT JOIN catalogue_field_value fv ON fv.object = m.id
            LEFT JOIN catalogue_field f ON f.id = fv.field
            WHERE imc.catalogue = 1
            GROUP BY m.id, m.name, cc.id, cc.name
            ORDER BY cc.name, m.name
          ');
          $stmt->execute();
          $productes = $stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <?php if (empty($productes)): ?>
            <p class="text-muted text-center py-4">Encara no hi ha productes. Afegeix-ne un amb el formulari.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle" style="font-size:.92rem;">
                <thead style="background:var(--color-secondary);">
                  <tr>
                    <th>Producte</th>
                    <th>Categoria</th>
                    <th>Preu</th>
                    <th>Unitat</th>
                    <th>Stock</th>
                    <th>Editar</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($productes as $p): ?>
                  <tr>
                    <td>
                      <?php if ($p['imatge']): ?>
                        <img src="assets/img/productes/<?= htmlspecialchars($p['imatge']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:.4rem;margin-right:.5rem;">
                      <?php endif; ?>
                      <strong><?= htmlspecialchars($p['name']) ?></strong>
                    </td>

                    <td><span style="font-size:.82rem;color:#666;"><?= htmlspecialchars($p['categoria']) ?></span></td>
                    <td><?= $p['preu'] ? htmlspecialchars($p['preu']) . ' €' : '—' ?></td>
                    <td><?= $p['unitat'] ? htmlspecialchars($p['unitat']) : '—' ?></td>
                    <td>
                      <form method="post" class="d-flex gap-1 align-items-center">
                        <input type="hidden" name="accio" value="stock">
                        <input type="hidden" name="id_model" value="<?= $p['id'] ?>">
                        <input type="number" name="nou_stock" value="<?= htmlspecialchars($p['stock'] ?? '') ?>"
                          class="form-control form-control-sm" style="width:70px;" min="0">
                        <button type="submit" class="btn btn-sm" style="background:var(--color-primary);color:#fff;border-radius:.4rem;">✓</button>
                      </form>
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius:.4rem;"
                        data-bs-toggle="modal" data-bs-target="#modalEditar"
                        data-id="<?= $p['id'] ?>"
                        data-nom="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                        data-cat="<?= htmlspecialchars($p['cat_id'] ?? '', ENT_QUOTES) ?>"
                        data-preu="<?= htmlspecialchars($p['preu'] ?? '', ENT_QUOTES) ?>"
                        data-stock="<?= htmlspecialchars($p['stock'] ?? '', ENT_QUOTES) ?>"
                        data-unitat="<?= htmlspecialchars($p['unitat'] ?? '', ENT_QUOTES) ?>"
                        data-imatge="<?= htmlspecialchars($p['imatge'] ?? '', ENT_QUOTES) ?>">✎</button>
                    </td>
                    <td>
                      <form method="post" onsubmit="return confirm('Eliminar <?= htmlspecialchars(addslashes($p['name'])) ?>?')">
                        <input type="hidden" name="accio" value="eliminar">
                        <input type="hidden" name="id_model" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:.4rem;">✕</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Categories -->
      <div class="col-12">
        <div class="targeta">
          <h5 style="color:var(--color-primary);font-weight:700;margin-bottom:1.25rem;">Categories</h5>
          <div class="row g-3 align-items-start">
            <div class="col-md-8">
              <?php
              $stmt = $db->prepare('
                SELECT cc.id, cc.name,
                  COUNT(imc.id) AS num_productes
                FROM catalogue_category cc
                LEFT JOIN catalogue_item_model_category imc ON imc.catalogue_category = cc.id
                WHERE cc.catalogue = 1
                GROUP BY cc.id, cc.name
                ORDER BY cc.name
              ');
              $stmt->execute();
              $categories_admin = $stmt->fetchAll(PDO::FETCH_ASSOC);
              ?>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($categories_admin as $c): ?>
                  <div class="d-flex align-items-center gap-1 px-3 py-2 rounded" style="background:var(--color-secondary);font-size:.9rem;">
                    <span style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></span>
                    <span style="color:#999;font-size:.8rem;">(<?= $c['num_productes'] ?>)</span>
                    <form method="post" class="d-inline ms-1" onsubmit="return confirm('Eliminar «<?= htmlspecialchars(addslashes($c['name'])) ?>»<?= $c['num_productes'] > 0 ? ' i els seus ' . $c['num_productes'] . ' productes' : '' ?>?')">
                      <input type="hidden" name="accio" value="eliminar_categoria">
                      <input type="hidden" name="id_cat" value="<?= $c['id'] ?>">
                      <button type="submit" class="btn btn-sm p-0" style="color:#dc3545;background:none;border:none;font-size:.85rem;">✕</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-4">
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="accio" value="afegir_categoria">
                <input type="text" class="form-control form-control-sm" name="nom_cat" required placeholder="Nova categoria…">
                <button type="submit" class="btn btn-sm" style="background:var(--color-primary);color:#fff;white-space:nowrap;border-radius:.4rem;">+ Afegir</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Formulari nou producte -->
      <div class="col-lg-4">
        <div class="targeta">
          <h6 style="color:var(--color-primary);font-weight:700;margin-bottom:1.25rem;">Afegir producte</h6>
          <?php
          $stmt = $db->prepare('SELECT id, name FROM catalogue_category WHERE catalogue = 1 ORDER BY name');
          $stmt->execute();
          $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accio" value="afegir">
            <div class="mb-3">
              <label class="form-label">Nom del producte *</label>
              <input type="text" class="form-control" name="nom" required placeholder="ex: Pollastre sencer">
            </div>
            <div class="mb-3">
              <label class="form-label">Categoria *</label>
              <select class="form-select" name="categoria" required onchange="this.form.nom_categoria.value=this.options[this.selectedIndex].text">
                <option value="">Selecciona…</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="nom_categoria" value="">
            </div>
            <div class="mb-3">
              <label class="form-label">Preu (€)</label>
              <input type="text" class="form-control" name="preu" placeholder="ex: 3.50">
            </div>
            <div class="mb-3">
              <label class="form-label">Unitat</label>
              <input type="text" class="form-control" name="unitat" placeholder="ex: kg, unitat, litre">
            </div>
            <div class="mb-3">
              <label class="form-label">Stock inicial</label>
              <input type="number" class="form-control" name="stock" placeholder="ex: 50" min="0">
            </div>
            <div class="mb-3">
              <label class="form-label">Imatge</label>
              <input type="file" class="form-control" name="imatge" accept="image/*">
              <div class="form-text">JPG, PNG, WEBP. Es redimensiona automàticament.</div>
            </div>
            <button type="submit" class="btn-comanda w-100" style="border-radius:.75rem;">Afegir producte</button>
          </form>
        </div>
      </div>

    </div>
  </div>

<?php if (isset($_SESSION['supermas_admin'])): ?>
<!-- Modal Editar Producte -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:1rem;">
      <div class="modal-header" style="background:var(--color-primary);color:#fff;border-radius:1rem 1rem 0 0;">
        <h6 class="modal-title fw-bold" id="modalEditarLabel">Editar producte</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form method="post" enctype="multipart/form-data" id="formEditar">
          <input type="hidden" name="accio" value="editar">
          <input type="hidden" name="id_model" id="edit_id_model">
          <input type="hidden" name="nom_categoria" id="edit_nom_categoria">

          <div class="mb-3">
            <label class="form-label fw-semibold">Nom del producte *</label>
            <input type="text" class="form-control" name="nom" id="edit_nom" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Categoria *</label>
            <select class="form-select" name="categoria" id="edit_categoria" required
              onchange="document.getElementById('edit_nom_categoria').value=this.options[this.selectedIndex].text">
              <option value="">Selecciona…</option>
              <?php
              $stmt2 = $db->prepare('SELECT id, name FROM catalogue_category WHERE catalogue = 1 ORDER BY name');
              $stmt2->execute();
              foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $c):
              ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">Preu (€)</label>
              <input type="text" class="form-control" name="preu" id="edit_preu" placeholder="ex: 3.50">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Unitat</label>
              <input type="text" class="form-control" name="unitat" id="edit_unitat" placeholder="ex: kg">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Stock</label>
            <input type="number" class="form-control" name="stock" id="edit_stock" min="0">
          </div>
          <div class="mb-3">
            <div id="edit_imatge_preview" class="mb-2" style="display:none;">
              <img id="edit_imatge_img" src="" style="height:70px;border-radius:.5rem;object-fit:cover;">
              <span id="edit_imatge_nom" style="font-size:.82rem;color:#666;margin-left:.5rem;"></span>
            </div>
            <label class="form-label fw-semibold">Canviar imatge</label>
            <input type="file" class="form-control" name="imatge_edit" accept="image/*">
            <div class="form-text">Deixa en blanc per mantenir la imatge actual.</div>
          </div>
          <div class="d-flex gap-2 justify-content-end pt-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel·lar</button>
            <button type="submit" class="btn-comanda" style="border-radius:2rem;padding:.45rem 1.5rem;">Guardar canvis</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('modalEditar').addEventListener('show.bs.modal', function(e) {
  var btn = e.relatedTarget;
  document.getElementById('edit_id_model').value = btn.dataset.id;
  document.getElementById('edit_nom').value       = btn.dataset.nom;
  document.getElementById('edit_preu').value      = btn.dataset.preu;
  document.getElementById('edit_stock').value     = btn.dataset.stock;
  document.getElementById('edit_unitat').value    = btn.dataset.unitat;

  var sel = document.getElementById('edit_categoria');
  sel.value = btn.dataset.cat;
  document.getElementById('edit_nom_categoria').value = sel.options[sel.selectedIndex]?.text || '';

  var imatge = btn.dataset.imatge;
  var prev = document.getElementById('edit_imatge_preview');
  if (imatge) {
    document.getElementById('edit_imatge_img').src = 'assets/img/productes/' + imatge;
    document.getElementById('edit_imatge_nom').textContent = imatge;
    prev.style.display = 'block';
  } else {
    prev.style.display = 'none';
  }
});
</script>
<?php endif; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
