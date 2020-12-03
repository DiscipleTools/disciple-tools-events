"use strict"
jQuery(document).ready(function($) {

    let post_id = window.detailsSettings.post_id
    let post_type = window.detailsSettings.post_type
    let post = window.detailsSettings.post_fields
    let field_settings = window.detailsSettings.post_settings.fields


    /* Member List*/
    let memberList = $('.member-list')
    let memberCountInput = $('#member_count')
    let leaderCountInput = $('#leader_count')
    let populateMembersList = ()=>{
        memberList.empty()

        post.members.forEach(m=>{
            if ( _.find( post.leaders || [], {ID: m.ID} ) ){
                m.leader = true
            }
        })
        post.members = _.sortBy( post.members, ["leader"])
        post.members.forEach(member=>{
            let leaderHTML = '';
            if( member.leader ){
                leaderHTML = `<i class="fi-foot small leader"></i>`
            }
            let memberHTML = `<div class="member-row" style="" data-id="${_.escape( member.ID )}">
          <div style="flex-grow: 1" class="member-status">
              <i class="fi-torso small"></i>
              <a href="${_.escape(window.wpApiShare.site_url)}/contacts/${_.escape( member.ID )}">${_.escape(member.post_title)}</a>
              ${leaderHTML}
          </div>
          <button class="button clear make-leader member-row-actions" data-id="${_.escape( member.ID )}">
            <i class="fi-foot small"></i>
          </button>
          <button class="button clear delete-member member-row-actions" data-id="${_.escape( member.ID )}">
            <i class="fi-x small"></i>
          </button>
        </div>`
            memberList.append(memberHTML)
        })
        if (post.members.length === 0) {
            $("#empty-members-list-message").show()
        } else {
            $("#empty-members-list-message").hide()
        }
        memberCountInput.val( post.member_count )
        leaderCountInput.val( post.leader_count )
        window.masonGrid.masonry('layout')
    }
    populateMembersList()

    $( document ).on( "dt-post-connection-created", function( e, new_post, field_key ){
        if ( field_key === "members" ){
            post = new_post
            populateMembersList()
        }
    } )
    $(document).on("click", ".delete-member", function () {
        let id = $(this).data('id')
        $(`.member-row[data-id="${id}"]`).remove()
        API.update_post( post_type, post_id, {'members': {values:[{value:id, delete:true}]}}).then(groupRes=>{
            post=groupRes
            populateMembersList()
            masonGrid.masonry('layout')
        })
        if( _.find( post.leaders || [], {ID: id}) ) {
            API.update_post( post_type, post_id, {'leaders': {values: [{value: id, delete: true}]}})
        }
    })
    $(document).on("click", ".make-leader", function () {
        let id = $(this).data('id')
        let remove = false
        let existingLeaderIcon = $(`.member-row[data-id="${id}"] .leader`)
        if( _.find( post.leaders || [], {ID: id}) || existingLeaderIcon.length !== 0){
            remove = true
            existingLeaderIcon.remove()
        } else {
            $(`.member-row[data-id="${id}"] .member-status`).append(`<i class="fi-foot small leader"></i>`)
        }
        API.update_post( post_type, post_id, {'leaders': {values:[{value:id, delete:remove}]}}).then(groupRes=>{
            post=groupRes
            populateMembersList()
            window.masonGrid.masonry('layout')
        })
    })
    $('.add-new-member').on("click", function () {
        $('#add-new-group-member-modal').foundation('open');
        Typeahead[`.js-typeahead-members`].adjustInputSize()
    })
    $( document ).on( "dt-post-connection-added", function( e, new_post, field_key ){
        post = new_post;
        if ( field_key === "members" ){
            populateMembersList()
        }
    })

    /* end Member List */


    /**
     * Assigned_to
     */
    let assigned_to_input = $(`.js-typeahead-assigned_to`)
    $.typeahead({
        input: '.js-typeahead-assigned_to',
        minLength: 0,
        maxItem: 0,
        accent: true,
        searchOnFocus: true,
        source: TYPEAHEADS.typeaheadUserSource(),
        templateValue: "{{name}}",
        template: function (query, item) {
            return `<div class="assigned-to-row" dir="auto">
        <span>
            <span class="avatar"><img style="vertical-align: text-bottom" src="{{avatar}}"/></span>
            ${_.escape( item.name )}
        </span>
        ${ item.status_color ? `<span class="status-square" style="background-color: ${_.escape(item.status_color)};">&nbsp;</span>` : '' }
        ${ item.update_needed && item.update_needed > 0 ? `<span>
          <img style="height: 12px;" src="${_.escape( window.wpApiShare.template_dir )}/dt-assets/images/broken.svg"/>
          <span style="font-size: 14px">${_.escape(item.update_needed)}</span>
        </span>` : '' }
      </div>`
        },
        dynamic: true,
        hint: true,
        emptyTemplate: _.escape(window.wpApiShare.translations.no_records_found),
        callback: {
            onClick: function(node, a, item){
                API.update_post( post_type, post_id, {assigned_to: 'user-' + item.ID}).then(function (response) {
                    _.set(post, "assigned_to", response.assigned_to)
                    assigned_to_input.val(post.assigned_to.display)
                    assigned_to_input.blur()
                }).catch(err => { console.error(err) })
            },
            onResult: function (node, query, result, resultCount) {
                let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
                $('#assigned_to-result-container').html(text);
            },
            onHideLayout: function () {
                $('.assigned_to-result-container').html("");
            },
            onReady: function () {
                if (_.get(post,  "assigned_to.display")){
                    $('.js-typeahead-assigned_to').val(post.assigned_to.display)
                }
            }
        },
    });
    $('.search_assigned_to').on('click', function () {
        assigned_to_input.val("")
        assigned_to_input.trigger('input.typeahead')
        assigned_to_input.focus()
    })

})
