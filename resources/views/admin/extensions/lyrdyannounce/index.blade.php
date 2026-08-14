@extends('layouts.admin')
<?php 
    // Define extension information.
    $EXTENSION_ID = "lyrdyannounce";
    $EXTENSION_NAME = stripslashes("Announce");
    $EXTENSION_VERSION = "1.1.0";
    $EXTENSION_DESCRIPTION = stripslashes("Layeredy Announce integration for Pterodactyl/Blueprint");
    $EXTENSION_ICON = "/assets/extensions/lyrdyannounce/icon.png";
    $EXTENSION_WEBSITE = "https://layeredy.com/announce";
    $EXTENSION_WEBICON = "bi bi-link-45deg";
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
    @yield('extension.description')<form id="config-form" action="" method="POST">
  <div class="row">
    <div class="col-xs-12 col-sm-12 col-md-4 col-lg-3">
      <img src="/extensions/lyrdyannounce/wordmark.png" style="width: 100%; filter: brightness(0) invert(1)"/>
      <br>
      This extension is an integration for <b>Layeredy Announce</b>. To utilize Announce, <a href="https://announce.layeredy.com/" target="_blank">make an account here</a>.
        <br />
        <p class="text-muted small">Copyright &copy; 2026 Layeredy Software</p>
        
        <hr style="border-top: 1px solid rgba(255,255,255,0.2); margin: 15px 0;">
        
        <h4 style="color: #fff; margin-top: 0;">Documentation</h4>
        <a href="https://docs.layeredy.com/announce-pterodactyl-guide" target="_blank" class="btn btn-primary btn-sm btn-block" style="margin-bottom: 10px;">
          <i class="fa fa-book"></i> View Integration Guide
        </a>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-8 col-lg-9">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">
            <i class="bi bi-toggles"></i>
            Toggle
          </h3>
        </div>
        <div class="box-body">
          <div class="col-xs-12">
            <label class="control-label text-truncate">
              Enable/disable Announce
            </label>

            <select class="form-control" name="enable">
              <option value="true" @if($toggle == "true") selected @endif>Enabled</option>
              <option value="false" @if($toggle == "false") selected @endif>Disabled</option>
            </select>
            <p class="text-muted small">
              Enable or disable Announce. If disabled, the Announce script will not be loaded on your site.
            </p>
          </div>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">
            <i class="bi bi-tags-fill"></i>
            Your Identifier
          </h3>
        </div>
        <div class="box-body">
          <div class="col-xs-12">
            <label class="control-label text-truncate">
              Identifier
            </label>

            <input type="text" name="user_id" id="user_id" value="{{ $user_id }}" placeholder="usr_test_1234567890" class="form-control"/>

            <p class="text-muted small">
              Your identifier (You'll need an <a href="https://announce.layeredy.com/" target="_blank">Announce</a> account)
            </p>
          </div>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">
            <i class="bi bi-key-fill"></i>
            API Key
          </h3>
        </div>
        <div class="box-body">
          <div class="col-xs-12">
            <label class="control-label text-truncate">
              Layeredy API Key (Optional)
            </label>

            <input type="password" name="api_key" id="api_key" value="{{ $api_key }}" placeholder="ldy_your_api_key" class="form-control"/>

            <p class="text-muted small">
              Your Layeredy API key for managing announcements. Generate one from your <a href="https://app.layeredy.com/settings?tab=api-keys" target="_blank">settings</a>. Required for the announcement manager below.
            </p>
          </div>
        </div>
      </div>

      <button type="submit" name="_method" value="PATCH" class="btn btn-primary btn-sm">Apply Changes</button>
      {{ csrf_field() }}
    </div>
  </div>
</form>

{{-- Announcement Management Section --}}
<div class="row" style="margin-top: 25px;">
  <div class="col-xs-12">
    <div id="announce-alert" class="alert" style="display:none;"></div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">
          <i class="fa fa-bullhorn"></i> Announcement Manager
        </h3>
        @if(!empty($api_key))
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#announcementModal" onclick="openCreateModal()">
              <i class="fa fa-plus"></i> New Announcement
            </button>
            <button type="button" class="btn btn-sm btn-default" onclick="location.reload(true)">
              <i class="fa fa-refresh"></i>
            </button>
          </div>
        @endif
      </div>
      @if(empty($api_key))
        <div class="box-body">
          <div class="text-center text-muted" style="padding: 30px 20px;">
            <i class="fa fa-key" style="font-size: 36px; display: block; margin-bottom: 10px;"></i>
            <p>Enter your Layeredy API key above and click <strong>Apply Changes</strong> to manage announcements here.</p>
          </div>
        </div>
      @else
        <div id="announcements-loading" class="box-body text-center text-muted" style="padding: 30px;">
          <i class="fa fa-spinner fa-spin"></i> Loading announcements...
        </div>
        <div id="announcements-table-wrap" style="display:none;">
          <div class="box-body table-responsive no-padding">
            <table class="table table-hover" id="announcements-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Content</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Theme</th>
                  <th class="text-center">Position</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="announcements-tbody"></tbody>
            </table>
          </div>
          <div id="announcements-pagination" class="box-footer with-border" style="display:none;">
            <div class="col-md-12 text-center" id="announcements-pagination-inner"></div>
          </div>
        </div>
        <div id="announcements-empty" class="box-body text-center text-muted" style="display:none; padding: 30px 20px;">
          <i class="fa fa-bullhorn" style="font-size: 36px; display: block; margin-bottom: 10px;"></i>
          <p>No announcements yet. Click <strong>New Announcement</strong> to create one.</p>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Create/Edit Modal (Bootstrap 3) --}}
<div class="modal fade" id="announcementModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="modal-title">New Announcement</h4>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ann-edit-id" value="">

        <div class="row">
          <div class="col-md-12">
            <div class="form-group">
              <label for="ann-name" class="control-label">Name <span class="text-danger">*</span></label>
              <input type="text" id="ann-name" class="form-control" placeholder="Announcement name" maxlength="50">
              <p class="text-muted small">Max 50 characters. Internal name for this announcement.</p>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <div class="form-group">
              <label for="ann-content" class="control-label">Content <span class="text-danger">*</span></label>
              <textarea id="ann-content" class="form-control" rows="3" placeholder="Announcement message" maxlength="250"></textarea>
              <p class="text-muted small">Max 250 characters. The message shown to visitors.</p>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-theme" class="control-label">Theme</label>
              <select id="ann-theme" class="form-control">
                <option value="default">Default</option>
                <option value="dark">Dark</option>
                <option value="light">Light</option>
                <option value="primary">Primary</option>
                <option value="success">Success</option>
                <option value="warning">Warning</option>
                <option value="danger">Danger</option>
                <option value="info">Info</option>
                <option value="purple">Purple</option>
                <option value="teal">Teal</option>
                <option value="pink">Pink</option>
                <option value="orange">Orange</option>
                <option value="custom">Custom (Plus plan)</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-position" class="control-label">Position</label>
              <select id="ann-position" class="form-control">
                <option value="top">Top</option>
                <option value="bottom">Bottom</option>
                <option value="push-content">Push Content</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row" id="custom-colors-row" style="display:none;">
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-custom-color" class="control-label">Custom Background Color</label>
              <input type="color" id="ann-custom-color" class="form-control" value="#4A90E2" style="height:34px; padding:2px 4px;">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-custom-text-color" class="control-label">Custom Text Color</label>
              <input type="color" id="ann-custom-text-color" class="form-control" value="#FFFFFF" style="height:34px; padding:2px 4px;">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="ann-opacity" class="control-label">Opacity</label>
              <input type="number" id="ann-opacity" class="form-control" value="1" min="0" max="1" step="0.1">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="ann-active" class="control-label">Active</label>
              <select id="ann-active" class="form-control">
                <option value="true">Yes</option>
                <option value="false">No</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="ann-dismissal" class="control-label">Allow Dismissal</label>
              <select id="ann-dismissal" class="form-control">
                <option value="true">Yes</option>
                <option value="false">No</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-show-once" class="control-label">Show Once</label>
              <select id="ann-show-once" class="form-control">
                <option value="false">No</option>
                <option value="true">Yes</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-link-url" class="control-label">Link URL</label>
              <input type="url" id="ann-link-url" class="form-control" placeholder="https://example.com">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-link-text" class="control-label">Link Text</label>
              <input type="text" id="ann-link-text" class="form-control" placeholder="Learn more">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-start-date" class="control-label">Start Date</label>
              <input type="datetime-local" id="ann-start-date" class="form-control">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="ann-end-date" class="control-label">End Date</label>
              <input type="datetime-local" id="ann-end-date" class="form-control">
              <p class="text-muted small">Optional. Leave empty for no end date.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        {!! csrf_field() !!}
        <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-save-announcement" onclick="saveAnnouncement()">
          <i class="fa fa-check"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Delete Confirmation Modal (Bootstrap 3) --}}
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Delete Announcement</h4>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="delete-ann-name"></strong>? This action cannot be undone.</p>
        <input type="hidden" id="delete-ann-id" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete()">
          <i class="fa fa-trash-o"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

@if(!empty($api_key))
<script>
(function() {
  var API_BASE = 'https://app.layeredy.com/api/v2';
  var API_KEY = @json($api_key);
  var currentPage = 1;

  function apiHeaders() {
    return {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + API_KEY,
    };
  }

  function showAlert(message, type) {
    var el = document.getElementById('announce-alert');
    el.className = 'alert alert-' + type;
    el.textContent = message;
    el.style.display = 'block';
    setTimeout(function() { el.style.display = 'none'; }, 5000);
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text || ''));
    return div.innerHTML;
  }

  window.loadAnnouncements = async function(page) {
    page = page || 1;
    currentPage = page;
    var loadingEl = document.getElementById('announcements-loading');
    var tableWrap = document.getElementById('announcements-table-wrap');
    var tbody = document.getElementById('announcements-tbody');
    var emptyEl = document.getElementById('announcements-empty');
    var pagWrap = document.getElementById('announcements-pagination');
    var pagInner = document.getElementById('announcements-pagination-inner');

    if (loadingEl) loadingEl.style.display = 'block';
    if (tableWrap) tableWrap.style.display = 'none';
    if (emptyEl) emptyEl.style.display = 'none';
    if (pagWrap) pagWrap.style.display = 'none';

    try {
      var res = await fetch(API_BASE + '/announcements?page=' + page + '&limit=10', {
        method: 'GET',
        headers: apiHeaders(),
      });
      var data = await res.json();

      if (loadingEl) loadingEl.style.display = 'none';

      if (!res.ok) {
        showAlert(data.error || 'Failed to load announcements.', 'danger');
        return;
      }

      if (!data.announcements || data.announcements.length === 0) {
        if (emptyEl) emptyEl.style.display = 'block';
        return;
      }

      var html = '';
      var announcements = {};
      data.announcements.forEach(function(ann) {
        announcements[ann.id] = ann;
        var statusLabel = ann.isActive
          ? '<span class="label label-success">Active</span>'
          : '<span class="label label-danger">Inactive</span>';
        var themeLabel = '<span class="label label-default">' + escapeHtml(ann.theme) + '</span>';
        var posLabel = '<span class="label label-primary">' + escapeHtml(ann.position) + '</span>';

        html += '<tr>'
          + '<td class="middle">' + escapeHtml(ann.name) + '</td>'
          + '<td class="middle"><small>' + escapeHtml(ann.content) + '</small></td>'
          + '<td class="middle text-center">' + statusLabel + '</td>'
          + '<td class="middle text-center">' + themeLabel + '</td>'
          + '<td class="middle text-center">' + posLabel + '</td>'
          + '<td class="middle text-right">'
          + '  <button class="btn btn-xs btn-primary" data-ann-id="' + escapeHtml(ann.id) + '"><i class="fa fa-pencil"></i></button>'
          + '  <button class="btn btn-xs btn-danger" data-ann-id="' + escapeHtml(ann.id) + '" data-ann-name="' + escapeHtml(ann.name) + '"><i class="fa fa-trash-o"></i></button>'
          + '</td>'
          + '</tr>';
      });
      if (tbody) tbody.innerHTML = html;
      window.announcementsCache = announcements;
      
      // Attach event listeners
      if (tbody) {
        var editButtons = tbody.querySelectorAll('button.btn-primary');
        editButtons.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var annId = this.getAttribute('data-ann-id');
            if (window.announcementsCache[annId]) {
              openEditModal(window.announcementsCache[annId]);
            }
          });
        });
        
        var deleteButtons = tbody.querySelectorAll('button.btn-danger');
        deleteButtons.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var annId = this.getAttribute('data-ann-id');
            var annName = this.getAttribute('data-ann-name');
            openDeleteConfirm(annId, annName);
          });
        });
      }
      if (tableWrap) tableWrap.style.display = 'block';

      // Pagination
      if (data.pagination && data.pagination.totalPages > 1) {
        var pagHtml = '<ul class="pagination pagination-sm" style="margin:0;">';
        for (var i = 1; i <= data.pagination.totalPages; i++) {
          pagHtml += '<li class="' + (i === page ? 'active' : '') + '">'
            + '<a href="#" onclick="event.preventDefault(); loadAnnouncements(' + i + ')">' + i + '</a></li>';
        }
        pagHtml += '</ul>';
        if (pagInner) pagInner.innerHTML = pagHtml;
        if (pagWrap) pagWrap.style.display = 'block';
      }
    } catch (err) {
      if (loadingEl) loadingEl.style.display = 'none';
      showAlert('Network error: Could not reach the Layeredy API.', 'danger');
      console.error('Announce API error:', err);
    }
  };

  // CREATE MODAL
  window.openCreateModal = function() {
    document.getElementById('modal-title').textContent = 'New Announcement';
    document.getElementById('ann-edit-id').value = '';
    document.getElementById('ann-name').value = '';
    document.getElementById('ann-content').value = '';
    document.getElementById('ann-theme').value = 'default';
    document.getElementById('ann-position').value = 'top';
    document.getElementById('ann-custom-color').value = '#4A90E2';
    document.getElementById('ann-custom-text-color').value = '#FFFFFF';
    document.getElementById('ann-opacity').value = '1';
    document.getElementById('ann-active').value = 'true';
    document.getElementById('ann-show-once').value = 'false';
    document.getElementById('ann-dismissal').value = 'true';
    document.getElementById('ann-link-url').value = '';
    document.getElementById('ann-link-text').value = '';
    document.getElementById('ann-start-date').value = '';
    document.getElementById('ann-end-date').value = '';
    toggleCustomColors();
  };

  // EDIT MODAL
  window.openEditModal = function(ann) {
    document.getElementById('modal-title').textContent = 'Edit Announcement';
    document.getElementById('ann-edit-id').value = ann.id;
    document.getElementById('ann-name').value = ann.name || '';
    document.getElementById('ann-content').value = ann.content || '';
    document.getElementById('ann-theme').value = ann.theme || 'default';
    document.getElementById('ann-position').value = ann.position || 'top';
    document.getElementById('ann-custom-color').value = ann.customColor || '#4A90E2';
    document.getElementById('ann-custom-text-color').value = ann.customTextColor || '#FFFFFF';
    document.getElementById('ann-opacity').value = ann.opacity != null ? ann.opacity : 1;
    document.getElementById('ann-active').value = ann.isActive ? 'true' : 'false';
    document.getElementById('ann-show-once').value = ann.showOnce ? 'true' : 'false';
    document.getElementById('ann-dismissal').value = ann.allowDismissal ? 'true' : 'false';
    document.getElementById('ann-link-url').value = ann.linkUrl || '';
    document.getElementById('ann-link-text').value = ann.linkText || '';

    if (ann.startDate) {
      document.getElementById('ann-start-date').value = ann.startDate.slice(0, 16);
    } else {
      document.getElementById('ann-start-date').value = '';
    }
    if (ann.endDate) {
      document.getElementById('ann-end-date').value = ann.endDate.slice(0, 16);
    } else {
      document.getElementById('ann-end-date').value = '';
    }

    toggleCustomColors();
    $('#announcementModal').modal('show');
  };

  function toggleCustomColors() {
    var theme = document.getElementById('ann-theme').value;
    document.getElementById('custom-colors-row').style.display = theme === 'custom' ? '' : 'none';
  }
  document.getElementById('ann-theme').addEventListener('change', toggleCustomColors);

  // SAVE
  window.saveAnnouncement = async function() {
    var editId = document.getElementById('ann-edit-id').value;
    var name = document.getElementById('ann-name').value.trim();
    var content = document.getElementById('ann-content').value.trim();

    if (!name) { showAlert('Announcement name is required.', 'warning'); return; }
    if (!content) { showAlert('Announcement content is required.', 'warning'); return; }

    var body = {
      name: name,
      content: content,
      theme: document.getElementById('ann-theme').value,
      position: document.getElementById('ann-position').value,
      opacity: parseFloat(document.getElementById('ann-opacity').value) || 1,
      isActive: document.getElementById('ann-active').value === 'true',
      showOnce: document.getElementById('ann-show-once').value === 'true',
      allowDismissal: document.getElementById('ann-dismissal').value === 'true',
    };

    if (body.theme === 'custom') {
      body.customColor = document.getElementById('ann-custom-color').value;
      body.customTextColor = document.getElementById('ann-custom-text-color').value;
    }

    var linkUrl = document.getElementById('ann-link-url').value.trim();
    var linkText = document.getElementById('ann-link-text').value.trim();
    if (linkUrl) {
      body.linkUrl = linkUrl;
      body.linkText = linkText || 'Learn more';
    }

    var startDate = document.getElementById('ann-start-date').value;
    var endDate = document.getElementById('ann-end-date').value;
    if (startDate) body.startDate = new Date(startDate).toISOString();
    if (endDate) body.endDate = new Date(endDate).toISOString();

    var saveBtn = document.getElementById('btn-save-announcement');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

    try {
      var url = API_BASE + '/announcements';
      var method = 'POST';
      if (editId) {
        url += '/' + editId;
        method = 'PUT';
      }

      var res = await fetch(url, {
        method: method,
        headers: apiHeaders(),
        body: JSON.stringify(body),
      });
      var data = await res.json();

      if (!res.ok) {
        showAlert(data.error || 'Failed to save announcement.', 'danger');
        return;
      }

      $('#announcementModal').modal('hide');
      showAlert('Saved! It may take a few minutes for changes to appear on your website!', 'success');
      setTimeout(function() { location.reload(true); }, 1500);
    } catch (err) {
      showAlert('Network error: Could not reach the Layeredy API.', 'danger');
      console.error('Announce save error:', err);
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fa fa-check"></i> Save';
    }
  };

  // DELETE
  window.openDeleteConfirm = function(id, name) {
    document.getElementById('delete-ann-id').value = id;
    document.getElementById('delete-ann-name').textContent = name;
    $('#deleteModal').modal('show');
  };

  window.confirmDelete = async function() {
    var id = document.getElementById('delete-ann-id').value;
    if (!id) return;

    try {
      var res = await fetch(API_BASE + '/announcements/' + id, {
        method: 'DELETE',
        headers: apiHeaders(),
      });
      var data = await res.json();

      if (!res.ok) {
        showAlert(data.error || 'Failed to delete announcement.', 'danger');
        return;
      }

      $('#deleteModal').modal('hide');
      showAlert('Saved! It may take a few minutes for changes to appear on your website!', 'success');
      setTimeout(function() { location.reload(true); }, 1500);
    } catch (err) {
      showAlert('Network error: Could not reach the Layeredy API.', 'danger');
      console.error('Announce delete error:', err);
    }
  };

  // Load on page ready
  loadAnnouncements();
})();
</script>
@endif
@endsection
