import React, { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Activity, Save, AlertTriangle, CheckCircle, Info, Loader2 } from 'lucide-react';

const VitalSignsForm = ({
  vitalSignTypes = [],
  onSubmit = null,
  initialData = null,
  isEditing = false,
  className = ""
}) => {
  const [formData, setFormData] = useState({
    vital_sign_type_id: '',
    value_primary: '',
    value_secondary: '',
    unit: '',
    measured_at: new Date().toISOString().slice(0, 16),
    notes: '',
    measurement_method: 'manual',
    device_name: '',
  });

  const [validationErrors, setValidationErrors] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitMessage, setSubmitMessage] = useState(null);
  const [valueWarning, setValueWarning] = useState(null);

  // Initialize form data
  useEffect(() => {
    if (initialData) {
      setFormData({
        vital_sign_type_id: initialData.vital_sign_type_id?.toString() || '',
        value_primary: initialData.value_primary?.toString() || '',
        value_secondary: initialData.value_secondary?.toString() || '',
        unit: initialData.unit || '',
        measured_at: initialData.measured_at ? new Date(initialData.measured_at).toISOString().slice(0, 16) : new Date().toISOString().slice(0, 16),
        notes: initialData.notes || '',
        measurement_method: initialData.measurement_method || 'manual',
        device_name: initialData.device_name || '',
      });
    }
  }, [initialData]);

  // Get selected vital sign type
  const selectedType = useMemo(() => {
    return vitalSignTypes.find(type => type.id === parseInt(formData.vital_sign_type_id));
  }, [vitalSignTypes, formData.vital_sign_type_id]);

  // Update unit when type changes
  useEffect(() => {
    if (selectedType) {
      setFormData(prev => ({
        ...prev,
        unit: selectedType.unit_primary
      }));

      // Clear secondary value if not needed
      if (!selectedType.has_secondary_value) {
        setFormData(prev => ({ ...prev, value_secondary: '' }));
      }
    } else {
      setFormData(prev => ({ ...prev, unit: '' }));
    }
  }, [selectedType]);

  // Clear device name when method changes
  useEffect(() => {
    if (formData.measurement_method !== 'device') {
      setFormData(prev => ({ ...prev, device_name: '' }));
    }
  }, [formData.measurement_method]);

  // Validate value against ranges
  const validateValue = (value) => {
    if (!selectedType || !value) {
      setValueWarning(null);
      return;
    }

    const numValue = parseFloat(value);

    if (selectedType.min_value && numValue < selectedType.min_value) {
      setValueWarning({
        type: 'error',
        message: `Value is below minimum safe range (${selectedType.min_value} ${selectedType.unit_primary})`
      });
      return;
    }

    if (selectedType.max_value && numValue > selectedType.max_value) {
      setValueWarning({
        type: 'error',
        message: `Value is above maximum safe range (${selectedType.max_value} ${selectedType.unit_primary})`
      });
      return;
    }

    if (selectedType.warning_range_min && selectedType.warning_range_max &&
        numValue >= selectedType.warning_range_min && numValue <= selectedType.warning_range_max) {
      setValueWarning({
        type: 'warning',
        message: 'Value is in the warning range'
      });
      return;
    }

    if (selectedType.normal_range_min && selectedType.normal_range_max &&
        (numValue < selectedType.normal_range_min || numValue > selectedType.normal_range_max)) {
      setValueWarning({
        type: 'info',
        message: 'Value is outside the normal range'
      });
      return;
    }

    setValueWarning(null);
  };

  // Handle form field changes
  const handleChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));

    // Clear validation error for this field
    if (validationErrors[field]) {
      setValidationErrors(prev => ({ ...prev, [field]: null }));
    }

    // Validate primary value
    if (field === 'value_primary') {
      validateValue(value);
    }
  };

  // Validate form
  const validateForm = () => {
    const errors = {};

    if (!formData.vital_sign_type_id) {
      errors.vital_sign_type_id = 'Please select a vital sign type';
    }

    if (!formData.value_primary) {
      errors.value_primary = 'Please enter a measurement value';
    } else if (isNaN(parseFloat(formData.value_primary))) {
      errors.value_primary = 'Please enter a valid number';
    } else if (parseFloat(formData.value_primary) < 0) {
      errors.value_primary = 'Value cannot be negative';
    }

    if (selectedType?.has_secondary_value && !formData.value_secondary) {
      errors.value_secondary = 'Secondary value is required for this measurement type';
    }

    if (formData.value_secondary && isNaN(parseFloat(formData.value_secondary))) {
      errors.value_secondary = 'Please enter a valid number';
    }

    if (!formData.unit) {
      errors.unit = 'Unit is required';
    }

    if (!formData.measured_at) {
      errors.measured_at = 'Please specify when the measurement was taken';
    } else {
      const measurementDate = new Date(formData.measured_at);
      const now = new Date();
      if (measurementDate > now) {
        errors.measured_at = 'Measurement date cannot be in the future';
      }
    }

    if (!formData.measurement_method) {
      errors.measurement_method = 'Please select a measurement method';
    }

    if (formData.measurement_method === 'device' && !formData.device_name.trim()) {
      errors.device_name = 'Device name is required for device measurements';
    }

    if (formData.notes && formData.notes.length > 1000) {
      errors.notes = 'Notes cannot exceed 1000 characters';
    }

    return errors;
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();

    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    setIsSubmitting(true);
    setSubmitMessage(null);

    try {
      const submitData = {
        ...formData,
        value_primary: parseFloat(formData.value_primary),
        value_secondary: formData.value_secondary ? parseFloat(formData.value_secondary) : null,
        vital_sign_type_id: parseInt(formData.vital_sign_type_id),
      };

      if (onSubmit) {
        await onSubmit(submitData);
      } else {
        // Default Inertia submission
        const url = isEditing ? `/api/vital-signs/${initialData?.id}` : '/api/vital-signs';
        const method = isEditing ? 'put' : 'post';

        router[method](url, submitData, {
          onSuccess: () => {
            setSubmitMessage({
              type: 'success',
              message: `Vital signs ${isEditing ? 'updated' : 'recorded'} successfully!`
            });
            if (!isEditing) {
              // Reset form for new entries
              setFormData({
                vital_sign_type_id: '',
                value_primary: '',
                value_secondary: '',
                unit: '',
                measured_at: new Date().toISOString().slice(0, 16),
                notes: '',
                measurement_method: 'manual',
                device_name: '',
              });
            }
          },
          onError: (errors) => {
            setValidationErrors(errors);
            setSubmitMessage({
              type: 'error',
              message: 'Failed to save vital signs. Please check the form for errors.'
            });
          }
        });
      }
    } catch (error) {
      setSubmitMessage({
        type: 'error',
        message: 'An unexpected error occurred. Please try again.'
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const getMessageIcon = (type) => {
    switch (type) {
      case 'success': return <CheckCircle className="h-5 w-5 text-green-400" />;
      case 'error': return <AlertTriangle className="h-5 w-5 text-red-400" />;
      case 'warning': return <AlertTriangle className="h-5 w-5 text-yellow-400" />;
      default: return <Info className="h-5 w-5 text-blue-400" />;
    }
  };

  const getMessageColors = (type) => {
    switch (type) {
      case 'success': return 'bg-green-50 border-green-200 text-green-800';
      case 'error': return 'bg-red-50 border-red-200 text-red-800';
      case 'warning': return 'bg-yellow-50 border-yellow-200 text-yellow-800';
      default: return 'bg-blue-50 border-blue-200 text-blue-800';
    }
  };

  return (
    <Card className={className}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Activity className="h-5 w-5" />
          {isEditing ? 'Edit Vital Signs' : 'Record Vital Signs'}
        </CardTitle>
        <CardDescription>
          {isEditing ? 'Update your health measurement' : 'Enter your health measurements below'}
        </CardDescription>
      </CardHeader>

      <CardContent>
        {/* Submit Message */}
        {submitMessage && (
          <div className={`mb-4 p-4 border rounded-md ${getMessageColors(submitMessage.type)}`}>
            <div className="flex">
              <div className="flex-shrink-0">
                {getMessageIcon(submitMessage.type)}
              </div>
              <div className="ml-3">
                <p className="text-sm font-medium">{submitMessage.message}</p>
              </div>
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Type and Method Row */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Measurement Type *
              </label>
              <Select
                value={formData.vital_sign_type_id}
                onValueChange={(value) => handleChange('vital_sign_type_id', value)}
              >
                <SelectTrigger className={validationErrors.vital_sign_type_id ? 'border-red-500' : ''}>
                  <SelectValue placeholder="Select measurement type..." />
                </SelectTrigger>
                <SelectContent>
                  {vitalSignTypes.map((type) => (
                    <SelectItem key={type.id} value={type.id.toString()}>
                      {type.display_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {validationErrors.vital_sign_type_id && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.vital_sign_type_id}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Measurement Method *
              </label>
              <Select
                value={formData.measurement_method}
                onValueChange={(value) => handleChange('measurement_method', value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="manual">Manual Entry</SelectItem>
                  <SelectItem value="device">Medical Device</SelectItem>
                  <SelectItem value="estimated">Estimated</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Device Name (conditional) */}
          {formData.measurement_method === 'device' && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Device Name *
              </label>
              <Input
                type="text"
                value={formData.device_name}
                onChange={(e) => handleChange('device_name', e.target.value)}
                placeholder="e.g., Omron BP Monitor, Apple Watch"
                className={validationErrors.device_name ? 'border-red-500' : ''}
              />
              {validationErrors.device_name && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.device_name}</p>
              )}
            </div>
          )}

          {/* Values Row */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                {selectedType ? `${selectedType.display_name} Value` : 'Measurement Value'} *
              </label>
              <div className="relative">
                <Input
                  type="number"
                  step="0.01"
                  value={formData.value_primary}
                  onChange={(e) => handleChange('value_primary', e.target.value)}
                  onBlur={() => validateValue(formData.value_primary)}
                  placeholder="Enter value"
                  className={validationErrors.value_primary ? 'border-red-500 pr-16' : 'pr-16'}
                />
                {formData.unit && (
                  <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span className="text-gray-500 text-sm">{formData.unit}</span>
                  </div>
                )}
              </div>
              {validationErrors.value_primary && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.value_primary}</p>
              )}

              {/* Range Info */}
              {selectedType && formData.value_primary && (
                <div className="mt-1 text-xs text-gray-500">
                  Normal range: {selectedType.normal_range_min ?? '?'} - {selectedType.normal_range_max ?? '?'} {formData.unit}
                </div>
              )}

              {/* Value Warning */}
              {valueWarning && (
                <div className={`mt-2 p-2 border rounded text-xs ${
                  valueWarning.type === 'error' ? 'bg-red-50 border-red-200 text-red-700' :
                  valueWarning.type === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-700' :
                  'bg-blue-50 border-blue-200 text-blue-700'
                }`}>
                  <div className="flex">
                    {valueWarning.type === 'error' ? (
                      <AlertTriangle className="h-3 w-3 mt-0.5 mr-1 flex-shrink-0" />
                    ) : (
                      <Info className="h-3 w-3 mt-0.5 mr-1 flex-shrink-0" />
                    )}
                    {valueWarning.message}
                  </div>
                </div>
              )}
            </div>

            {/* Secondary Value (conditional) */}
            {selectedType?.has_secondary_value && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Secondary Value *
                </label>
                <div className="relative">
                  <Input
                    type="number"
                    step="0.01"
                    value={formData.value_secondary}
                    onChange={(e) => handleChange('value_secondary', e.target.value)}
                    placeholder="Secondary value"
                    className={validationErrors.value_secondary ? 'border-red-500 pr-16' : 'pr-16'}
                  />
                  {selectedType.unit_secondary && (
                    <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                      <span className="text-gray-500 text-sm">{selectedType.unit_secondary}</span>
                    </div>
                  )}
                </div>
                {validationErrors.value_secondary && (
                  <p className="mt-1 text-sm text-red-600">{validationErrors.value_secondary}</p>
                )}
              </div>
            )}

            {/* Measurement Date/Time */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                When measured? *
              </label>
              <Input
                type="datetime-local"
                value={formData.measured_at}
                onChange={(e) => handleChange('measured_at', e.target.value)}
                max={new Date().toISOString().slice(0, 16)}
                className={validationErrors.measured_at ? 'border-red-500' : ''}
              />
              {validationErrors.measured_at && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.measured_at}</p>
              )}
            </div>
          </div>

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Additional Notes
            </label>
            <textarea
              value={formData.notes}
              onChange={(e) => handleChange('notes', e.target.value)}
              rows={3}
              placeholder="Any additional context or notes about this measurement..."
              className={`w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 ${
                validationErrors.notes ? 'border-red-500' : ''
              }`}
            />
            {validationErrors.notes && (
              <p className="mt-1 text-sm text-red-600">{validationErrors.notes}</p>
            )}
            <p className="mt-1 text-sm text-gray-500">
              {formData.notes.length}/1000 characters
            </p>
          </div>

          {/* Submit Button */}
          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={isSubmitting}
              className="min-w-32"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Saving...
                </>
              ) : (
                <>
                  <Save className="h-4 w-4 mr-2" />
                  {isEditing ? 'Update' : 'Record'} Vital Signs
                </>
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
};

export default VitalSignsForm;