<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ogTitulo }}</title>
    <meta name="description" content="{{ $ogDescricao }}">
    <meta property="og:type" content="website">
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
    @if($ogImagem['width'])
    <meta property="og:image:width" content="{{ $ogImagem['width'] }}">
    @endif
    @if($ogImagem['height'])
    <meta property="og:image:height" content="{{ $ogImagem['height'] }}">
    @endif
    <meta property="og:image:alt" content="{{ $ogTitulo }}">
    @endif
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:site_name" content="Comprovante PIX Bradesco">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitulo }}">
    <meta name="twitter:description" content="{{ $ogDescricao }}">
    @if($ogImagem['url'])
    <meta name="twitter:image" content="{{ $ogImagem['url'] }}">
    @endif
</head>
<body></body>
</html>
