import React, {Fragment} from 'react'

import {trans} from '#/main/app/intl/translation'
import {PropTypes as T, implementPropTypes} from '#/main/app/prop-types'
import {CALLBACK_BUTTON, MODAL_BUTTON} from '#/main/app/buttons'
import {Button} from '#/main/app/action/components/button'

import {FormField as FormFieldTypes} from '#/main/core/layout/form/prop-types'
import {EmptyPlaceholder} from '#/main/core/layout/components/placeholder'
import {LocationCard} from '#/main/core/user/data/components/location-card'
import {Location as LocationType} from '#/main/core/user/prop-types'
import {MODAL_LOCATIONS} from '#/main/core/modals/locations'

const LocationsButton = props =>
  <Button
    className="btn btn-block"
    style={{marginTop: 10}}
    type={MODAL_BUTTON}
    icon="fa fa-fw fa-plus"
    label={trans('select_a_location')}
    disabled={props.disabled}
    modal={[MODAL_LOCATIONS, {
      url: ['apiv2_location_list'],
      title: props.title,
      selectAction: (selected) => ({
        type: CALLBACK_BUTTON,
        label: trans('select', {}, 'actions'),
        callback: () => props.onChange(selected[0])
      })
    }]}
  />

LocationsButton.propTypes = {
  title: T.string,
  disabled: T.bool,
  onChange: T.func.isRequired
}

const LocationInput = props => {
  if (props.value) {
    return (
      <Fragment>
        <LocationCard
          data={props.value}
          actions={[
            {
              name: 'delete',
              type: CALLBACK_BUTTON,
              icon: 'fa fa-fw fa-trash-o',
              label: trans('delete', {}, 'actions'),
              dangerous: true,
              disabled: props.disabled,
              callback: () => props.onChange(null)
            }
          ]}
        />

        <LocationsButton
          {...props.picker}
          disabled={props.disabled}
          onChange={props.onChange}
        />
      </Fragment>
    )
  }

  return (
    <EmptyPlaceholder
      icon="fa fa-location-arrow"
      title={trans('no_location')}
    >
      <LocationsButton
        {...props.picker}
        disabled={props.disabled}
        onChange={props.onChange}
      />
    </EmptyPlaceholder>
  )
}

implementPropTypes(LocationInput, FormFieldTypes, {
  value: T.shape(LocationType.propTypes),
  picker: T.shape({
    title: T.string
  })
}, {
  value: null
})

export {
  LocationInput
}