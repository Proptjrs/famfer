<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Panne momentanée · FamFer</title>

{{--
  Cette page est volontairement autonome.

  Elle n'étend pas le gabarit du site, et ce n'est pas une négligence : le
  gabarit interroge la base de données pour composer la barre des rayons et
  compter les litiges ouverts. Or une erreur 500 est le plus souvent causée par
  une base indisponible — s'appuyer sur le gabarit déclencherait donc une
  seconde erreur à l'intérieur de la première, et le visiteur verrait la page
  blanche du serveur.

  Pour la même raison, le style est écrit ici plutôt que chargé depuis
  « famfer.css » : si le disque ou le serveur de fichiers est en cause, la
  feuille ne se chargerait pas davantage. Une page d'erreur doit tenir debout
  toute seule, sinon elle n'est pas une page d'erreur.
--}}
<style>
  :root {
    --canvas:#F3F5F6; --surface:#FFFFFF; --line:#DEE3E7;
    --ink:#14181B; --ink-3:#616B73; --brand:#F5871F; --on-brand:#1A1206;
    --brand-strong:#A8560A;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --canvas:#0F1214; --surface:#171B1E; --line:#2B3338;
      --ink:#EAEEF0; --ink-3:#939DA5; --brand:#F79B3E; --on-brand:#171004;
      --brand-strong:#F9AE5E;
    }
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, "Segoe UI", Roboto, system-ui, sans-serif;
    background: var(--canvas); color: var(--ink);
    line-height: 1.55; min-height: 100vh;
    display: grid; place-items: center; padding: 1.5rem;
    -webkit-font-smoothing: antialiased;
  }
  .boite {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: 10px; padding: 2.5rem 2rem;
    max-width: 34rem; width: 100%; text-align: center;
  }
  .sigle {
    display: inline-flex; align-items: center; gap: .5rem;
    font-weight: 800; font-size: 1.25rem; letter-spacing: -.03em;
    margin-bottom: 1.5rem;
  }
  .sigle span {
    width: 1.75rem; height: 1.75rem; display: grid; place-items: center;
    background: var(--brand); color: var(--on-brand);
    border-radius: 6px; font-size: .875rem; letter-spacing: 0;
  }
  .sigle em { font-style: normal; color: var(--brand-strong); }
  .code {
    font-family: ui-monospace, "SF Mono", Consolas, monospace;
    font-size: 2.25rem; font-weight: 600; color: var(--ink-3);
    letter-spacing: .06em;
  }
  h1 { font-size: 1.375rem; margin: .5rem 0 .75rem; letter-spacing: -.011em; }
  p { color: var(--ink-3); font-size: .9375rem; }
  p + p { margin-top: .75rem; }
  a.btn {
    display: inline-flex; align-items: center; justify-content: center;
    margin-top: 1.75rem; padding: .75rem 1.5rem; min-height: 2.75rem;
    background: var(--brand); color: var(--on-brand);
    border-radius: 6px; font-weight: 650; text-decoration: none;
  }
  a.btn:hover { filter: brightness(.94); }
  a.btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
</style>
</head>
<body>

<div class="boite">
  <div class="sigle"><span aria-hidden="true">FF</span>FAM<em>FER</em></div>

  <div class="code">500</div>
  <h1>Panne momentanée</h1>

  <p>
    Quelque chose s'est cassé de notre côté, pas du vôtre. L'incident est
    enregistré et nous le regardons.
  </p>
  <p>
    <strong>Vos commandes en cours ne sont pas affectées</strong>, et rien ne
    vous a été prélevé : sur FamFer, le règlement se fait à la livraison.
  </p>

  <a href="/" class="btn">Réessayer</a>
</div>

</body>
</html>
