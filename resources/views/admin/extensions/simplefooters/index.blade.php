@extends('layouts.admin')
<?php 
    // Define extension information.
    $EXTENSION_ID = "simplefooters";
    $EXTENSION_NAME = stripslashes("Simple Footers");
    $EXTENSION_VERSION = "v1.0.3";
    $EXTENSION_DESCRIPTION = stripslashes("Create and edit a simple footer at the bottom of your panel");
    $EXTENSION_ICON = "/assets/extensions/simplefooters/icon.png";
    $EXTENSION_WEBSITE = "[website]";
    $EXTENSION_WEBICON = "[webicon]";
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
    @yield('extension.description')<div class="row">
  <div class="col-xs-12">
    <p>Version: <code>v1.0.3</code><br/>
    Author: <code>PatrickYu17</code><br/>
    Discord: <code>pyuwu17</code><br/>
    Identifier: <code>simplefooters</code></p>
  </div>
  <form action="" method="POST">

    <!-- Toggle status -->
    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Toggle Status</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-xs-12">
              <label class="control-label">Status</label>
              <select class="form-control" name="status">
                <option value="true" @if($db_status == "true") selected @endif>Enabled</option>
                <option value="false" @if($db_status == "false") selected @endif>Disabled</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Font Size</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-xs-12">
              <label class="control-label">Font Size</label>
                <input type="text" required name="fontsize" id="fontsize" value="{{$fontsize}}" class="form-control"/>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Font Color</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-xs-12">
              <label class="control-label">Font Color</label>
                <input type="color" name="fontcolor" id="fontcolor" value="{{$fontcolor}}" placeholder="#606D7B" class="form-control"/>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Custom Footer</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="col-xs-12">
              <label class="control-label">Input</label>
              <p>This input field accepts HTML code for custom footer, formatting, and styling. If this isn't for you, just input whatever text you want this extension to display and alter the settings with this extension.</p>
              <textarea required name="text" id="text" rows="10" class="form-control" maxlength="999999999999">{{ $text }}</textarea>
             <!-- <input type="text" required name="text" id="text" value="{{ $text }}" class="form-control"/>-->
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-xs-12">
      {{ csrf_field() }}
      <button type="submit" name="_method" value="PATCH" class="btn btn-gray-alt btn-sm pull-right">Apply</button>
    </div>
  </form> 
</div>
@endsection
