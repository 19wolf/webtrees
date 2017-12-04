<!-- A dynamic modal, with content loaded using AJAX. -->
<div class="modal fade" id="wt-ajax-modal" tabindex="-1" role="dialog" aria-labelledBy="wt-ajax-modal-title" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content"></div>
	</div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    $('#wt-ajax-modal').on('show.bs.modal', function (event) {
      let modal_content = this.querySelector('.modal-content');

      // If we need to paste the result into a select2 control
      modal_content.dataset.selectId = event.relatedTarget.dataset.selectId;

      // Clear existing content (to prevent FOUC) and load new content.
      $(modal_content)
        .empty()
        .load(event.relatedTarget.dataset.href);
    });
  });
</script>
