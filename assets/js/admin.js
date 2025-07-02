jQuery(document).ready(function ($) {
  // Handle form selection change
  $("#cf7_form_select").on("change", function () {
    var formId = $(this).val();
    var fieldSelect = $("#cf7_field_select");

    console.log("CF7 form selection changed to:", formId);

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
    console.log("Making AJAX call for form ID:", formId);
    $.ajax({
      url: cf7PropstackAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "get_cf7_fields",
        form_id: formId,
        nonce: cf7PropstackAdmin.nonce,
      },
      success: function (response) {
        console.log("AJAX response:", response);
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
      error: function (xhr, status, error) {
        console.log("AJAX error:", { xhr: xhr, status: status, error: error });
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
          // Optionally show a success message
          // alert(response.data);
          updateMappingsList();
          // Optionally reset the dropdowns
          $("#cf7_field_select").val("");
          $("#propstack_field_select").val("");
        } else {
          alert(response.data);
        }
      },
      error: function () {
        alert(cf7PropstackAdmin.strings.errorSavingMapping);
      },
    });
  });

  // Function to update the mappings list
  function updateMappingsList() {
    var formId = $("#cf7_form_select").val();
    $.ajax({
      url: cf7PropstackAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "get_field_mappings",
        form_id: formId,
        nonce: cf7PropstackAdmin.nonce,
      },
      success: function (response) {
        if (response.success && response.data && response.data.html) {
          $(".mappings-list").html(response.data.html);
          disableMappedFields(); // Disable already mapped fields
        }
      },
    });
  }

  // Disable already mapped fields in dropdowns
  function disableMappedFields() {
    // Get mapped CF7 field values from the table (should match the value attribute)
    var mappedCF7 = [];
    $(".mappings-list tbody tr").each(function () {
      var cf7Field = $(this).find("td:nth-child(2)").text().trim();
      if (cf7Field) mappedCF7.push(cf7Field);
    });

    // Disable mapped CF7 fields in the dropdown by value
    $("#cf7_field_select option").each(function () {
      var val = $(this).val();
      if (mappedCF7.includes(val)) {
        $(this).prop("disabled", true);
      } else {
        $(this).prop("disabled", false);
      }
    });

    // Get mapped Propstack field values from the table (should match the value attribute)
    var mappedPropstack = [];
    $(".mappings-list tbody tr").each(function () {
      var propstackField = $(this).find("td:nth-child(3)").text().trim();
      if (propstackField) mappedPropstack.push(propstackField);
    });

    // Disable mapped Propstack fields in the dropdown by value
    $("#propstack_field_select option").each(function () {
      var val = $(this).val();
      if (mappedPropstack.includes(val)) {
        $(this).prop("disabled", true);
      } else {
        $(this).prop("disabled", false);
      }
    });
  }

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

  // Handle refresh custom fields button
  $("#refresh_custom_fields").on("click", function () {
    var button = $(this);
    var originalText = button.text();
    var propstackFieldSelect = $("#propstack_field_select");

    // Show loading state
    button
      .text(cf7PropstackAdmin.strings.refreshing || "Refreshing...")
      .prop("disabled", true);
    propstackFieldSelect.prop("disabled", true);

    $.ajax({
      url: cf7PropstackAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "refresh_custom_fields",
        nonce: cf7PropstackAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Update the dropdown with new fields
          var currentValue = propstackFieldSelect.val();
          var options =
            '<option value="">' +
            (cf7PropstackAdmin.strings.selectField || "Select a field") +
            "</option>";

          // Add standard fields (these are hardcoded in PHP)
          var standardFields = {
            first_name: "First Name",
            last_name: "Last Name",
            email: "Email",
            salutation: "Salutation",
            academic_title: "Academic Title",
            company: "Company",
            position: "Position",
            home_phone: "Home Phone",
            home_cell: "Home Cell",
            office_phone: "Office Phone",
            office_cell: "Office Cell",
            description: "Description",
            language: "Language",
            newsletter: "Newsletter",
            accept_contact: "Accept Contact",
            client_source_id: "Client Source ID",
            client_status_id: "Client Status ID",
          };

          // Add standard fields
          $.each(standardFields, function (field, label) {
            options += '<option value="' + field + '">' + label + "</option>";
          });

          // Add custom fields from response
          if (response.data && response.data.fields) {
            $.each(response.data.fields, function (field, label) {
              options += '<option value="' + field + '">' + label + "</option>";
            });
          }

          propstackFieldSelect.html(options);

          // Restore previous selection if it still exists
          if (
            currentValue &&
            propstackFieldSelect.find('option[value="' + currentValue + '"]')
              .length
          ) {
            propstackFieldSelect.val(currentValue);
          }

          alert(
            response.data.message || "Custom fields refreshed successfully!"
          );
        } else {
          alert(response.data || "Failed to refresh custom fields");
        }
      },
      error: function () {
        alert(
          cf7PropstackAdmin.strings.errorRefreshingFields ||
            "Error refreshing custom fields"
        );
      },
      complete: function () {
        // Restore button state
        button.text(originalText).prop("disabled", false);
        propstackFieldSelect.prop("disabled", false);
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

  // --- CF7 Propstack Integration Panel Logic (migrated from inline script) ---

  function cf7PropstackPanelInit() {
    // Only run on the CF7 Propstack panel
    if (!document.getElementById("cf7_propstack_nonce")) return;

    var $ = jQuery;

    // Function to update CF7 field dropdown to disable already mapped fields
    function updateCF7FieldDropdown() {
      var mappedFields = [];
      $(".mappings-list tbody tr").each(function () {
        var cf7Field = $(this).find("td:first").text().trim();
        var fieldName = getFieldNameFromDisplayText(cf7Field);
        if (fieldName) {
          mappedFields.push(fieldName);
        }
      });
      $("#cf7_field_select option").each(function () {
        var optionValue = $(this).val();
        if (mappedFields.indexOf(optionValue) !== -1) {
          $(this).prop("disabled", true);
        } else {
          $(this).prop("disabled", false);
        }
      });
    }

    function getFieldNameFromDisplayText(displayText) {
      return displayText.toLowerCase().replace(/\s+/g, "-");
    }

    function getCF7FieldDisplayText(fieldName) {
      var option = $("#cf7_field_select option[value='" + fieldName + "']");
      return option.length ? option.text() : fieldName;
    }

    function getPropstackFieldDisplayText(fieldName) {
      var option = $(
        "#propstack_field_select option[value='" + fieldName + "']"
      );
      return option.length ? option.text() : fieldName;
    }

    function addMappingRow(cf7Field, propstackField) {
      var cf7DisplayText = getCF7FieldDisplayText(cf7Field);
      var propstackDisplayText = getPropstackFieldDisplayText(propstackField);
      var formId = $("#add_mapping").data("form-id");
      var newRow = $("<tr>").html(
        "<td>" +
          cf7DisplayText +
          "</td>" +
          "<td>" +
          propstackDisplayText +
          "</td>" +
          "<td>" +
          '<button type="button" class="button button-small delete-mapping" ' +
          'data-form-id="' +
          formId +
          '" ' +
          'data-cf7-field="' +
          cf7Field +
          '" ' +
          'data-propstack-field="' +
          propstackField +
          '">' +
          (window.cf7PropstackDeleteText || "Delete") +
          "</button>" +
          "</td>"
      );
      $(".mappings-list tbody").append(newRow);
      $("#cf7_field_select").val("");
      $("#propstack_field_select").val("");
      updateCF7FieldDropdown();
    }

    // Initialize dropdown state
    updateCF7FieldDropdown();

    // Add mapping
    $(document).on("click", "#add_mapping", function (e) {
      e.preventDefault();
      var formId = $(this).data("form-id");
      var cf7Field = $("#cf7_field_select").val();
      var propstackField = $("#propstack_field_select").val();
      var nonce = $("#cf7_propstack_nonce").val();
      var ajaxUrl = $("#cf7_propstack_ajax_url").val();
      var button = $(this);
      var originalText = button.text();
      if (!cf7Field || !propstackField) {
        alert(
          window.cf7PropstackSelectBothText ||
            "Please select both CF7 and Propstack fields."
        );
        return;
      }
      button
        .text(window.cf7PropstackAddingText || "Adding...")
        .prop("disabled", true);
      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: {
          action: "save_field_mapping",
          form_id: formId,
          cf7_field: cf7Field,
          propstack_field: propstackField,
          nonce: nonce,
        },
        success: function (response) {
          if (response.success) {
            addMappingRow(cf7Field, propstackField);
            showMessage(
              window.cf7PropstackMappingAddedText ||
                "Mapping added successfully!",
              "success"
            );
          } else {
            alert(
              response.data ||
                window.cf7PropstackFailedSaveText ||
                "Failed to save mapping."
            );
          }
        },
        error: function () {
          alert(
            window.cf7PropstackErrorSaveText ||
              "Error saving mapping. Please try again."
          );
        },
        complete: function () {
          button.text(originalText).prop("disabled", false);
        },
      });
    });

    // Delete mapping
    $(document).on("click", ".delete-mapping", function (e) {
      e.preventDefault();
      if (
        !confirm(
          window.cf7PropstackConfirmDeleteText ||
            "Are you sure you want to delete this mapping?"
        )
      ) {
        return;
      }
      var formId = $(this).data("form-id");
      var cf7Field = $(this).data("cf7-field");
      var propstackField = $(this).data("propstack-field");
      var nonce = $("#cf7_propstack_nonce").val();
      var ajaxUrl = $("#cf7_propstack_ajax_url").val();
      var button = $(this);
      button
        .prop("disabled", true)
        .text(window.cf7PropstackDeletingText || "Deleting...");
      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: {
          action: "delete_field_mapping_by_fields",
          form_id: formId,
          cf7_field: cf7Field,
          propstack_field: propstackField,
          nonce: nonce,
        },
        success: function (response) {
          if (response.success) {
            button.closest("tr").fadeOut(function () {
              $(this).remove();
              if ($(".mappings-list tbody tr").length === 0) {
                $(".mappings-list").html(
                  "<p>" +
                    (window.cf7PropstackNoMappingsText ||
                      "No field mappings configured for this form.") +
                    "</p>"
                );
              }
              updateCF7FieldDropdown();
            });
            showMessage(
              window.cf7PropstackMappingDeletedText ||
                "Mapping deleted successfully!",
              "success"
            );
          } else {
            alert(
              response.data ||
                window.cf7PropstackFailedDeleteText ||
                "Failed to delete mapping."
            );
          }
        },
        error: function () {
          alert(
            window.cf7PropstackErrorDeleteText ||
              "Error deleting mapping. Please try again."
          );
        },
        complete: function () {
          button
            .prop("disabled", false)
            .text(window.cf7PropstackDeleteText || "Delete");
        },
      });
    });

    // Show success/error messages
    function showMessage(message, type) {
      var messageClass = type === "success" ? "success" : "error";
      var messageHtml =
        '<div class="cf7-propstack-message ' +
        messageClass +
        '">' +
        message +
        "</div>";
      $(".cf7-propstack-mappings-section").prepend(messageHtml);
      setTimeout(function () {
        $(".cf7-propstack-message").fadeOut(function () {
          $(this).remove();
        });
      }, 3000);
    }
  }

  // Run the panel logic on DOM ready
  jQuery(document).ready(cf7PropstackPanelInit);
});
