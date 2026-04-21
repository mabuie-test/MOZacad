<!doctype html>
<html lang="pt-MZ">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Moz Acad') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-4"><div class="container"><a class="navbar-brand" href="/">Moz Acad</a></div></nav>
<div class="container"><?php include $contentView; ?></div>
</body>
</html>
