jQuery(function($){
    function initPickers($scope){ $scope.find('.cmp-color-field').wpColorPicker(); }
    initPickers($(document));
  
    $('#cmp-add-row').on('click', e=>{
      e.preventDefault();
      const $tbody = $('#cmp-cats-table tbody');
      const idx = $tbody.find('tr').length;
      const $tr = $(`
        <tr>
          <td><input name="cmp_categories[${idx}][name]" /></td>
          <td><input name="cmp_categories[${idx}][color]" class="cmp-color-field" /></td>
          <td><button class="button cmp-remove-row">Remove</button></td>
        </tr>
      `);
      $tbody.append($tr);
      initPickers($tr);
    });
  
    $(document).on('click', '.cmp-remove-row', function(e){
      e.preventDefault();
      $(this).closest('tr').remove();
      $('#cmp-cats-table tbody tr').each((i,tr)=>{
        $(tr).find('input').each(function(){
          const field = $(this).attr('name').split(']')[1];
          $(this).attr('name', `cmp_categories[${i}]${field}`);
        });
      });
    });
  });
  