@extends('layouts.admin')
<?php 
    // Define extension information.
    $EXTENSION_ID = "mctools";
    $EXTENSION_NAME = stripslashes("McTools");
    $EXTENSION_VERSION = "1.1.0";
    $EXTENSION_DESCRIPTION = stripslashes("Some helpful tools for Minecraft servers.");
    $EXTENSION_ICON = "/assets/extensions/mctools/icon.png";
    $EXTENSION_WEBSITE = "https://github.com/towsifkafi/mctools-petrodactyl";
    $EXTENSION_WEBICON = "bi bi-github";
?>
@include('blueprint.admin.template')

@section('title')
    {{ $EXTENSION_NAME }}
@endsection

@section('content-header')
    @yield('extension.header')
@endsection

@section('content')
    @yield('extension.config')
    @yield('extension.description')<!-- 
  Content on this page will be displayed on your extension's
  admin page.
-->

@php
$owner = 'towsifkafi';
$repo = 'mctools-pterodactyl';
try {
    $ch = curl_init("https://api.github.com/repos/{$owner}/{$repo}/releases/latest");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'PHP',
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json'],
        CURLOPT_FAILONERROR => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $latest = json_decode($response)->tag_name ?? 'No release found';
} catch (Exception $e) {
    $latest = 'Error: ' . $e->getMessage();
}
@endphp

<div class="box box-info">
  <div class="box-header with-border">
    <h3 class="box-title">Addon Information</h3>
  </div>
  <div class="box-body">
    <p>
      <code>mctools</code> is the identifier of this extension. <br>
      The current version is <code>v1.1.0</code>. <br>
      The latest version from GitHub is <code><a href="https://github.com/{{ $owner }}/{{ $repo }}/releases/tag/{{ $latest }}">{{ $latest }}</a></code>.
    </p>
  </div>
</div>
@endsection
