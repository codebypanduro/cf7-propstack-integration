jQuery(document).ready(function ($) {
  // Handle form selection change
  $("#cf7_form_select").on("change", function () {
    var formId = $(this).val();
    var fieldSelect = $("#cf7_field_select");

    if (!formId) {
      fieldSelect
        .html(
          '<option value="">' +
            cf7PropstackAdmin.strings.selectFormFirst +
            "</option>"
        )
        .prop("disabled", true);
      return;
    }

    // Get form fields via AJAX
    $.ajax({
      url: cf7PropstackAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "get_cf7_fields",
        form_id: formId,
        nonce: cf7PropstackAdmin.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          var options =
            '<option value="">' +
            cf7PropstackAdmin.strings.selectField +
            "</option>";
          $.each(response.data, function (field, label) {
            options += '<option value="' + field + '">' + label + "</option>";
          });
          fieldSelect.html(options).prop("disabled", false);
        } else {
          fieldSelect
            .html(
              '<option value="">' +
                cf7PropstackAdmin.strings.noFieldsFound +
                "</option>"
            )
            .prop("disabled", true);
        }
      },
      error: function () {
        fieldSelect
          .html(
            '<option value="">' +
              cf7PropstackAdmin.strings.errorLoadingFields +
              "</option>"
          )
          .prop("disabled", true);
      },
    });
  });

  // Handle add mapping button
  $("#add_mapping").on("click", function () {
    var formId = $("#cf7_form_select").val();
    var cf7Field = $("#cf7_field_select").val();
    var propstackField = $("#propstack_field_select").val();

    if (!formId || !cf7Field || !propstackField) {
      alert(cf7PropstackAdmin.strings.allFieldsRequired);
      return;
    }

    $.ajax({
      url: cf7PropstackAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "save_field_mapping",
        form_id: formId,
        cf7_field: cf7Field,
        propstack_field: propstackField,
        nonce: cf7PropstackAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          alert(response.data);
          location.reload(); // Reload to show new mapping
        } else {
          alert(response.data);
        }
      },
      error: function () {
        alert(cf7PropstackAdmin.strings.errorSavingMapping);
      },
    });
  });

  // Handle delete mapping buttons
  $(".delete-mapping").on("click", function () {
    if (!confirm(cf7PropstackAdmin.strings.confirmDelete)) {
      return;
    }

    var mappingId = $(this).data("id");
    var button = $(this);

    $.ajax({
      url: cf7PropstackAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "delete_field_mapping",
        mapping_id: mappingId,
        nonce: cf7PropstackAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          button.closest("tr").fadeOut(function () {
            $(this).remove();
            if ($(".mappings-list tbody tr").length === 0) {
              $(".mappings-list").html(
                "<p>" + cf7PropstackAdmin.strings.noMappingsConfigured + "</p>"
              );
            }
          });
        } else {
          alert(response.data);
        }
      },
      error: function () {
        alert(cf7PropstackAdmin.strings.errorDeletingMapping);
      },
    });
  });

  // Add some styling improvements
  $(".cf7-propstack-admin-container").addClass("cf7-propstack-styled");

  // Add tooltips for better UX
  $(".cf7-propstack-mappings select").each(function () {
    $(this).attr("title", $(this).find("option:first").text());
  });

  // Highlight required fields
  $(".cf7-propstack-mappings select").on("change", function () {
    if ($(this).val()) {
      $(this).removeClass("required-field");
    } else {
      $(this).addClass("required-field");
    }
  });

  // Add loading states
  $("#cf7_form_select").on("change", function () {
    $("#cf7_field_select")
      .html(
        '<option value="">' + cf7PropstackAdmin.strings.loading + "</option>"
      )
      .prop("disabled", true);
  });

  $("#add_mapping").on("click", function () {
    var button = $(this);
    var originalText = button.text();
    button.text(cf7PropstackAdmin.strings.saving).prop("disabled", true);

    // Re-enable button after a delay (in case of error)
    setTimeout(function () {
      button.text(originalText).prop("disabled", false);
    }, 5000);
  });

  // Add keyboard shortcuts
  $(document).on("keydown", function (e) {
    // Ctrl/Cmd + Enter to add mapping
    if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
      e.preventDefault();
      $("#add_mapping").click();
    }
  });

  // Add form validation
  function validateMappingForm() {
    var isValid = true;
    var requiredFields = [
      "#cf7_form_select",
      "#cf7_field_select",
      "#propstack_field_select",
    ];

    requiredFields.forEach(function (field) {
      if (!$(field).val()) {
        $(field).addClass("error");
        isValid = false;
      } else {
        $(field).removeClass("error");
      }
    });

    return isValid;
  }

  // Validate on form submission
  $("#add_mapping").on("click", function (e) {
    if (!validateMappingForm()) {
      e.preventDefault();
      alert(cf7PropstackAdmin.strings.pleaseFillAllFields);
      return false;
    }
  });

  // Remove error class when user starts typing/selecting
  $(".cf7-propstack-mappings select").on("change", function () {
    $(this).removeClass("error");
  });

  // Add help text
  $(".cf7-propstack-mappings").prepend(
    '<div class="notice notice-info">' +
      "<p><strong>" +
      cf7PropstackAdmin.strings.helpTitle +
      "</strong></p>" +
      "<p>" +
      cf7PropstackAdmin.strings.helpText +
      "</p>" +
      "</div>"
  );

  // Add export/import functionality placeholder
  $(".cf7-propstack-mappings").append(
    '<div class="cf7-propstack-import-export">' +
      "<h3>" +
      cf7PropstackAdmin.strings.importExport +
      "</h3>" +
      "<p>" +
      cf7PropstackAdmin.strings.importExportText +
      "</p>" +
      '<button type="button" class="button" id="export_mappings">' +
      cf7PropstackAdmin.strings.exportMappings +
      "</button> " +
      '<button type="button" class="button" id="import_mappings">' +
      cf7PropstackAdmin.strings.importMappings +
      "</button>" +
      "</div>"
  );

  // Handle export
  $("#export_mappings").on("click", function () {
    // This would need a server-side handler to generate the export
    alert(cf7PropstackAdmin.strings.exportFeature + " - Coming soon!");
  });

  // Handle import
  $("#import_mappings").on("click", function () {
    // This would need a file upload handler
    alert(cf7PropstackAdmin.strings.importFeature + " - Coming soon!");
  });
});
