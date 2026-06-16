<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comanda Online — Supermas</title>
  <meta name="description" content="Fes la teva comanda online a Supermas. T'ho portem a casa.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/main.css">
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-supermas sticky-top">
    <div class="container">
      <a class="navbar-brand" href="index.html"><img src="assets/img/supermas-logo.png" alt="Supermas" style="height:40px;"></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav ms-auto align-items-center gap-1">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="qui-som.html" data-bs-toggle="dropdown">Qui som</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="qui-som.html#empresa">Empresa</a></li>
              <li><a class="dropdown-item" href="qui-som.html#compromis">Compromís</a></li>
              <li><a class="dropdown-item" href="qui-som.html#establiments">Establiments</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="qualitat.html" data-bs-toggle="dropdown">Qualitat</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="qualitat.html#carnisseria">Carnisseria</a></li>
              <li><a class="dropdown-item" href="qualitat.html#fruites">Fruites i verdures</a></li>
              <li><a class="dropdown-item" href="qualitat.html#bodega">Bodega</a></li>
              <li><a class="dropdown-item" href="qualitat.html#peixateria">Peixateria</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="sostenibilitat.html" data-bs-toggle="dropdown">Sostenibilitat</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="sostenibilitat.html#locals">Productes locals</a></li>
              <li><a class="dropdown-item" href="sostenibilitat.html#ecologic">Alimentació ecològica</a></li>
              <li><a class="dropdown-item" href="sostenibilitat.html#comerc-just">Comerç just</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="salut.html" data-bs-toggle="dropdown">Salut</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="salut.html#sense-gluten">Sense gluten</a></li>
              <li><a class="dropdown-item" href="salut.html#super-sa">Súper sa</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="serveis.html" data-bs-toggle="dropdown">Serveis</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="serveis.html#atencio">Atenció al client</a></li>
              <li><a class="dropdown-item" href="serveis.html#entrega">Entrega a domicili</a></li>
              <li><a class="dropdown-item active" href="comanda.php">Comanda online</a></li>
            </ul>
          </li>
          <li class="nav-item ms-2">
            <a class="btn btn-comanda px-3 py-2" href="comanda.php" style="font-size:.85rem;">Comanda online</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="hero-interior">
    <div class="container">
      <h1>Comanda Online</h1>
      <p class="lead">Tria els teus productes i te'ls portem a casa. Fàcil i còmode.</p>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0" style="font-size:.85rem;">
          <li class="breadcrumb-item"><a href="index.html" style="color:var(--color-primary);">Inici</a></li>
          <li class="breadcrumb-item"><a href="serveis.html" style="color:var(--color-primary);">Serveis</a></li>
          <li class="breadcrumb-item active">Comanda online</li>
        </ol>
      </nav>
    </div>
  </div>

  <section class="seccio">
    <div class="container">
      <?php
      // Connexió a la BD del Manager
      $db = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=manager;charset=utf8mb4',
        'manager',
        'M4n4g3r_T1nn0v0!',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
      );

      // Carreguem categories del catàleg Supermas (id=1)
      $stmt = $db->prepare('SELECT id, name FROM catalogue_category WHERE catalogue = 1 AND catalogue_category = -1 ORDER BY id');
      $stmt->execute();
      $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Per cada categoria, carreguem els productes (catalogue_item_model) amb els seus camps dinàmics
      $productes_per_cat = [];
      foreach ($categories as $cat) {
        $stmt = $db->prepare('
          SELECT m.id, m.name,
            MAX(CASE WHEN f.name = "preu"   THEN fv.value END) AS preu,
            MAX(CASE WHEN f.name = "stock"  THEN fv.value END) AS stock,
            MAX(CASE WHEN f.name = "unitat" THEN fv.value END) AS unitat
          FROM catalogue_item_model m
          INNER JOIN catalogue_item_model_category imc ON m.catalogue_item_model_category = imc.id
          LEFT JOIN catalogue_field_value fv ON fv.object = m.id
          LEFT JOIN catalogue_field f ON f.id = fv.field
          WHERE imc.catalogue_category = :cat_id
          GROUP BY m.id, m.name
          ORDER BY m.name
        ');
        $stmt->execute([':cat_id' => $cat['id']]);
        $productes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($productes)) {
          $productes_per_cat[$cat['id']] = [
            'nom' => $cat['name'],
            'productes' => $productes
          ];
        }
      }

      $te_productes = !empty($productes_per_cat);
      ?>

      <div class="row g-5">
        <div class="col-lg-8">
          <form method="post" action="mailto:supermas@supermas.cat" enctype="text/plain">

            <!-- Dades personals -->
            <div class="mb-4">
              <div class="form-section-title">1. Les teves dades</div>
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label fw-500">Nom i cognoms *</label>
                  <input type="text" class="form-control" name="nom" required placeholder="Anna García López">
                </div>
                <div class="col-sm-6">
                  <label class="form-label fw-500">Telèfon *</label>
                  <input type="tel" class="form-control" name="telefon" required placeholder="93 000 00 00">
                </div>
                <div class="col-12">
                  <label class="form-label fw-500">Correu electrònic *</label>
                  <input type="email" class="form-control" name="email" required placeholder="nom@exemple.cat">
                </div>
                <div class="col-12">
                  <label class="form-label fw-500">Adreça de lliurament *</label>
                  <input type="text" class="form-control" name="adreca" required placeholder="C/ Exemple, 12, 3r 2a · Igualada">
                </div>
              </div>
            </div>

            <!-- Productes -->
            <div class="mb-4">
              <div class="form-section-title">2. Productes</div>

              <?php if ($te_productes): ?>
                <?php foreach ($productes_per_cat as $cat_id => $cat): ?>
                  <div class="mb-4">
                    <div style="font-weight:600;color:var(--color-primary);margin-bottom:.75rem;font-size:1rem;">
                      <?= htmlspecialchars($cat['nom']) ?>
                    </div>
                    <?php foreach ($cat['productes'] as $p): ?>
                      <?php
                        $stock = $p['stock'] !== null ? (int)$p['stock'] : null;
                        $sense_stock = $stock === null || $stock > 0;
                      ?>
                      <?php if ($sense_stock): ?>
                        <div class="producte-row">
                          <label>
                            <?= htmlspecialchars($p['name']) ?>
                            <?php if ($p['preu']): ?>
                              <span style="color:var(--color-accent);font-weight:700;margin-left:.5rem;">
                                <?= htmlspecialchars($p['preu']) ?> €
                              </span>
                            <?php endif; ?>
                            <?php if ($p['unitat']): ?>
                              <span style="color:#888;font-size:.85rem;margin-left:.25rem;">
                                / <?= htmlspecialchars($p['unitat']) ?>
                              </span>
                            <?php endif; ?>
                          </label>
                          <input type="number" class="form-control form-control-sm"
                            name="prod_<?= $p['id'] ?>"
                            min="0"
                            <?= ($stock !== null ? 'max="' . $stock . '"' : '') ?>
                            placeholder="0">
                        </div>
                      <?php else: ?>
                        <div class="producte-row" style="opacity:.45;">
                          <label>
                            <?= htmlspecialchars($p['name']) ?>
                            <span style="font-size:.8rem;color:#999;margin-left:.5rem;">(sense estoc)</span>
                          </label>
                          <input type="number" class="form-control form-control-sm" disabled placeholder="0">
                        </div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>

              <?php else: ?>
                <div class="p-4 rounded text-center" style="background:var(--color-secondary);">
                  <div style="font-size:2.5rem;margin-bottom:.75rem;">🛒</div>
                  <p class="mb-1" style="font-weight:600;color:var(--color-primary);">Catàleg en preparació</p>
                  <p class="mb-0 text-muted" style="font-size:.9rem;">Estem preparant el catàleg de productes. Mentrestant pots fer la teva comanda per telèfon o email.</p>
                </div>
                <div class="mt-3">
                  <label class="form-label fw-500">Detall de la comanda</label>
                  <textarea class="form-control" name="productes" rows="5" placeholder="Indica els productes que necessites: nom, quantitat, gramatge..."></textarea>
                </div>
              <?php endif; ?>

            </div>

            <!-- Lliurament -->
            <div class="mb-4">
              <div class="form-section-title">3. Lliurament</div>
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label">Data preferida</label>
                  <input type="date" class="form-control" name="data">
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Franja horària</label>
                  <select class="form-select" name="horari">
                    <option value="">Selecciona una franja</option>
                    <option>Matí: 9:00 – 13:00</option>
                    <option>Tarda: 17:00 – 20:00</option>
                    <option>Indistint</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Mètode de pagament</label>
                  <div class="d-flex gap-4 mt-1">
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="pagament" id="pag_efectiu" value="Efectiu" checked>
                      <label class="form-check-label" for="pag_efectiu">💵 Efectiu</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="pagament" id="pag_targeta" value="Targeta">
                      <label class="form-check-label" for="pag_targeta">💳 Targeta</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Observacions -->
            <div class="mb-4">
              <div class="form-section-title">4. Observacions</div>
              <textarea class="form-control" name="observacions" rows="3" placeholder="Al·lèrgies, preferències, instruccions d'accés…"></textarea>
            </div>

            <button type="submit" class="btn-comanda w-100" style="border-radius:.75rem;">Enviar comanda</button>
            <p class="text-muted text-center mt-3" style="font-size:.85rem;">
              Rebràs una confirmació per correu electrònic.
              Per dubtes: <a href="tel:938043400">93 804 34 00</a>
            </p>

          </form>
        </div>

        <div class="col-lg-4">
          <div class="targeta mb-4">
            <h6 style="color:var(--color-primary);font-weight:700;margin-bottom:1rem;">💰 Preus del servei</h6>
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
              <span>Preparació de comanda</span><strong>3,00 €</strong>
            </div>
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
              <span>Enviament &lt; 80 €</span><strong>3,50 €</strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>Enviament ≥ 80 €</span><strong style="color:var(--color-primary);">Gratuït</strong>
            </div>
          </div>
          <div class="targeta mb-4">
            <h6 style="color:var(--color-primary);font-weight:700;margin-bottom:1rem;">⏰ Horaris</h6>
            <p style="font-size:.9rem;margin-bottom:.5rem;">Comandes fins les <strong>18:00h</strong> → entrega el mateix dia.</p>
            <p style="font-size:.9rem;margin-bottom:.5rem;"><strong>Dissabtes</strong> → hora màxima 13:00h.</p>
            <p style="font-size:.9rem;margin-bottom:0;">Fora d'horari → entrega l'endemà.</p>
          </div>
          <div class="targeta">
            <h6 style="color:var(--color-primary);font-weight:700;margin-bottom:1rem;">📞 Contacte directe</h6>
            <a href="tel:938043400" style="font-size:1.1rem;font-weight:700;color:var(--color-accent);text-decoration:none;">93 804 34 00</a>
            <p style="font-size:.85rem;color:#888;margin-top:.5rem;margin-bottom:0;">
              o escriu-nos a<br>
              <a href="mailto:supermas@supermas.cat">supermas@supermas.cat</a>
            </p>
          </div>
          <div class="mt-3 text-end">
            <a href="admin.php" class="btn btn-outline-secondary btn-sm rounded-pill" style="font-size:.8rem;">⚙️ Stock</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="py-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-3">
          <img src="assets/img/supermas-logo.png" alt="Supermas" style="height:36px;filter:brightness(0) invert(1);display:block;margin-bottom:1rem;">
          <p style="opacity:.8;font-size:.9rem;">Som d'aquí, fem Anoia.<br>Empresa familiar des de 1980.</p>
        </div>
        <div class="col-md-3">
          <div class="footer-title">Establiments</div>
          <p style="opacity:.8;font-size:.9rem;">C/ Sant Vicenç, 31 · Igualada<br>Psg. Verdaguer, 178 · Igualada<br>C/ Sta. Llúcia, 13 · Vilanova del Camí</p>
        </div>
        <div class="col-md-3">
          <div class="footer-title">Horaris</div>
          <p style="opacity:.8;font-size:.9rem;">Dl–Dj: 8:30–13:30 i 17:00–20:30<br>Dv, Ds i vigílies: 8:30–20:30</p>
        </div>
        <div class="col-md-3">
          <div class="footer-title">Contacte</div>
          <p style="opacity:.8;font-size:.9rem;"><a href="mailto:supermas@supermas.cat">supermas@supermas.cat</a><br>93 804 34 00<br><br><a href="#">Facebook</a> · <a href="#">Instagram</a></p>
        </div>
      </div>
      <hr style="border-color:rgba(255,255,255,.2);margin-top:2rem;">
      <p class="text-center mb-0" style="opacity:.5;font-size:.8rem;">© 2026 Supermas · Tots els drets reservats</p>
    </div>
  </footer>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
