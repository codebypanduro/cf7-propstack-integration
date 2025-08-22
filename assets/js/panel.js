console.log("CF7 Propstack panel.js loaded");

jQuery(document).ready(function ($) {
  // Only run on the CF7 Propstack panel
  if (!document.getElementById("cf7_propstack_nonce")) {
    console.log("CF7 Propstack: No nonce found, exiting");
    return;
  }
  
  console.log("CF7 Propstack: panel.js initialized successfully");
  console.log("CF7 Propstack: Test API button exists:", $("#test_properties_api").length);
  console.log("CF7 Propstack: Refresh buttons exist:", $("#refresh_properties").length, $("#refresh_client_sources").length);

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
    var option = $("#propstack_field_select option[value='" + fieldName + "']");
    return option.length ? option.text() : fieldName;
  }

  function addNoMappingsRow() {
    var newRow = $("<tr>").html(
      "<td colspan='3'>" +
        (window.cf7PropstackNoMappingsText ||
          "No field mappings configured for this form.") +
        "</td>"
    );
    $(".mappings-list tbody").append(newRow);
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
    // remove default no mappings row
    $(".no-mappings-row").remove();
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
            // if there are no mappings left, add the no mappings row
            if ($(".mappings-list tbody tr").length === 0) {
              addNoMappingsRow();
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

  // Handle refresh properties button
  $(document).on("click", "#refresh_properties", function (e) {
    console.log("CF7 Propstack: Refresh properties button clicked");
    e.preventDefault();

    var button = $(this);
    var originalText = button.text();
    button.text(window.cf7PropstackPanelL10n?.refreshingText || "Refreshing...").prop("disabled", true);

    var nonce = $("#cf7_propstack_nonce").val();
    var ajaxUrl = $("#cf7_propstack_ajax_url").val();

    if (!nonce || !ajaxUrl) {
      alert(window.cf7PropstackPanelL10n?.missingNonceText || "Missing nonce or AJAX URL. Please refresh the page.");
      button.text(originalText).prop("disabled", false);
      return;
    }

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "refresh_properties",
        nonce: nonce,
      },
      success: function (response) {
        console.log("CF7 Propstack: Refresh properties success:", response);
        if (response.success && response.data.properties) {
          var select = $("#wpcf7-propstack-property-id");
          var selectedValue = select.val();

          // Clear existing options except the first one
          select.find("option:not(:first)").remove();

          // Add new options
          $.each(response.data.properties, function (index, property) {
            var title =
              property.title || property.name || "Property #" + property.id;
            select.append(
              '<option value="' + property.id + '">' + title + "</option>"
            );
          });

          // Restore selected value if it still exists
          select.val(selectedValue);

          showMessage(
            response.data.message || window.cf7PropstackPanelL10n?.propertiesRefreshedText || "Properties refreshed successfully!",
            "success"
          );
        } else {
          alert(response.data || window.cf7PropstackPanelL10n?.failedRefreshPropertiesText || "Failed to refresh properties.");
        }
      },
      error: function (xhr, status, error) {
        console.log("CF7 Propstack: Refresh properties error:", status, error);
        alert(window.cf7PropstackPanelL10n?.errorRefreshText || "Error refreshing properties. Please try again.");
      },
      complete: function () {
        button.text(originalText).prop("disabled", false);
      },
    });
  });

  // Handle refresh client sources button
  $(document).on("click", "#refresh_client_sources", function (e) {
    console.log("CF7 Propstack: Refresh client sources button clicked");
    e.preventDefault();

    var button = $(this);
    var originalText = button.text();
    button.text(window.cf7PropstackPanelL10n?.refreshingText || "Refreshing...").prop("disabled", true);

    var nonce = $("#cf7_propstack_nonce").val();
    var ajaxUrl = $("#cf7_propstack_ajax_url").val();

    if (!nonce || !ajaxUrl) {
      alert(window.cf7PropstackPanelL10n?.missingNonceText || "Missing nonce or AJAX URL. Please refresh the page.");
      button.text(originalText).prop("disabled", false);
      return;
    }

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "refresh_client_sources",
        nonce: nonce,
      },
      success: function (response) {
        console.log("CF7 Propstack: Refresh client sources success:", response);
        if (response.success && response.data.client_sources) {
          var select = $("#wpcf7-propstack-client-source-id");
          var selectedValue = select.val();

          // Clear existing options except the first one
          select.find("option:not(:first)").remove();

          // Add new options
          $.each(response.data.client_sources, function (index, source) {
            var name = source.name || source.title || "Source #" + source.id;
            select.append(
              '<option value="' + source.id + '">' + name + "</option>"
            );
          });

          // Restore selected value if it still exists
          select.val(selectedValue);

          showMessage(
            response.data.message || window.cf7PropstackPanelL10n?.clientSourcesRefreshedText || "Client sources refreshed successfully!",
            "success"
          );
        } else {
          alert(response.data || window.cf7PropstackPanelL10n?.failedRefreshClientSourcesText || "Failed to refresh client sources.");
        }
      },
      error: function (xhr, status, error) {
        console.log("CF7 Propstack: Refresh client sources error:", status, error);
        alert(window.cf7PropstackPanelL10n?.errorRefreshText || "Error refreshing client sources. Please try again.");
      },
      complete: function () {
        button.text(originalText).prop("disabled", false);
      },
    });
  });

  // Handle test properties API button
  $(document).on("click", "#test_properties_api", function (e) {
    console.log("CF7 Propstack: Test API button clicked");
    e.preventDefault();

    var button = $(this);
    var originalText = button.text();
    var nonce = $("#cf7_propstack_nonce").val();
    var ajaxUrl = $("#cf7_propstack_ajax_url").val();

    console.log("CF7 Propstack: Test API - nonce:", nonce, "ajaxUrl:", ajaxUrl);

    if (!nonce || !ajaxUrl) {
      alert(window.cf7PropstackPanelL10n?.missingNonceText || "Missing nonce or AJAX URL. Please refresh the page.");
      return;
    }

    button.text(window.cf7PropstackPanelL10n?.testingText || "Testing...").prop("disabled", true);

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "test_properties_api",
        nonce: nonce,
      },
      success: function (response) {
        console.log("CF7 Propstack: Test API success response:", response);
        if (response.success && response.data.debug_info) {
          var info = response.data.debug_info;
          var message = "API Test Results:\n\n";
          message +=
            "API Key Configured: " + (info.api_key_configured ? "Yes" : "No") + "\n";
          message += "Response Type: " + info.response_type + "\n";
          message += "Response Count: " + info.response_count + "\n";
          message +=
            "Raw Response: " + JSON.stringify(info.api_response, null, 2);

          alert(message);
        } else {
          alert(response.data || window.cf7PropstackPanelL10n?.testFailedText || "Test failed.");
        }
      },
      error: function (xhr, status, error) {
        console.log(
          "CF7 Propstack: Test API AJAX error:",
          status,
          error,
          xhr.responseText
        );
        alert(window.cf7PropstackPanelL10n?.errorTestText || "Error testing API. Please try again.");
      },
      complete: function () {
        button.text(originalText).prop("disabled", false);
      },
    });
  });

  // Handle clear cache button
  $(document).on("click", "#clear_propstack_cache", function (e) {
    console.log("CF7 Propstack: Clear cache button clicked");
    e.preventDefault();

    var button = $(this);
    var originalText = button.text();
    button.text("Clearing...").prop("disabled", true);

    var nonce = $("#cf7_propstack_nonce").val();
    var ajaxUrl = $("#cf7_propstack_ajax_url").val();

    if (!nonce || !ajaxUrl) {
      alert(window.cf7PropstackPanelL10n?.missingNonceText || "Missing nonce or AJAX URL. Please refresh the page.");
      button.text(originalText).prop("disabled", false);
      return;
    }

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "clear_propstack_cache",
        nonce: nonce,
      },
      success: function (response) {
        console.log("CF7 Propstack: Clear cache success:", response);
        if (response.success) {
          showMessage(
            response.data.message || "Cache cleared successfully!",
            "success"
          );
          // Reload the page to show fresh data
          setTimeout(function() {
            location.reload();
          }, 1000);
        } else {
          alert(response.data || "Failed to clear cache.");
        }
      },
      error: function (xhr, status, error) {
        console.log("CF7 Propstack: Clear cache error:", status, error);
        alert("Error clearing cache. Please try again.");
      },
      complete: function () {
        button.text(originalText).prop("disabled", false);
      },
    });
  });
});
