<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $ogTitulo }}</title>
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $ogTitulo }}">
    <meta property="og:description" content="{{ $ogDescricao }}">
    @if($ogImagem['url'])
    <meta property="og:image" content="{{ $ogImagem['url'] }}">
    @if(str_starts_with($ogImagem['url'], 'https://'))
    <meta property="og:image:secure_url" content="{{ $ogImagem['url'] }}">
    @endif
    @if($ogImagem['type'])
    <meta property="og:image:type" content="{{ $ogImagem['type'] }}">
    @endif
    <meta property="og:image:alt" content="{{ $ogTitulo }}">
    @endif
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta name="twitter:card" content="summary_large_image">
</head>
<body></body>
</html>
