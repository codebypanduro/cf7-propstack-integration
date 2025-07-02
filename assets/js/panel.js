console.log("CF7 Propstack panel.js loaded");

jQuery(document).ready(function ($) {
  // Only run on the CF7 Propstack panel
  if (!document.getElementById("cf7_propstack_nonce")) return;

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
});
