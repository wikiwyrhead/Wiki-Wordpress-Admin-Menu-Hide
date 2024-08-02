document.addEventListener("DOMContentLoaded", function () {
  const selectedRole = document.getElementById("camh-selected-role");
  const settingsForm = document.getElementById("camh-settings-form");

  selectedRole.addEventListener("change", function () {
    fetchSettingsForRole(this.value);
  });

  settingsForm.addEventListener("submit", function (e) {
    e.preventDefault();
    saveSettings();
  });

  function fetchSettingsForRole(role) {
    jQuery
      .post(
        camh_ajax.ajaxurl,
        {
          action: "camh_get_role_settings",
          role: role,
          security: camh_ajax.nonce,
        },
        function (response) {
          if (response.success) {
            updateSettingsForm(response.data);
          } else {
            console.error("Failed to fetch settings:", response.data);
          }
        }
      )
      .fail(function (xhr, textStatus, errorThrown) {
        console.error("AJAX error:", textStatus, errorThrown);
      });
  }

  function updateSettingsForm(settings) {
    const checkboxes = document.querySelectorAll(
      'input[name="camh_hidden_menus[]"]'
    );
    checkboxes.forEach(function (checkbox) {
      checkbox.checked = settings.hidden_menus.includes(checkbox.value);
    });

    document.querySelector(
      'input[name="camh_show_mode"][value="hide"]'
    ).checked = settings.show_mode === "hide";
    document.querySelector(
      'input[name="camh_show_mode"][value="show"]'
    ).checked = settings.show_mode === "show";

    document.querySelector('input[name="camh_hide_admin_notices"]').checked =
      settings.hide_admin_notices === "1";
  }

  function saveSettings() {
    const formData = new FormData(settingsForm);

    jQuery.ajax({
      url: camh_ajax.ajaxurl,
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.success) {
          alert("Settings saved successfully.");
        } else {
          alert("Failed to save settings.");
        }
      },
      error: function (xhr, textStatus, errorThrown) {
        console.error("AJAX error:", textStatus, errorThrown);
      },
    });
  }

  // Fetch settings for the initially selected role on page load
  fetchSettingsForRole(selectedRole.value);

  // Search menu items
  const menuSearch = document.getElementById("camh-menu-search");
  const menuItems = document.querySelectorAll("#camh-menu-items li");

  menuSearch.addEventListener("input", function () {
    const filter = menuSearch.value.toLowerCase();
    menuItems.forEach(function (item) {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(filter) ? "" : "none";
    });
  });
});
