import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/Components/ui/form';
import { useForm as useReactHookForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

const formSchema = z.object({
  vital_sign_type_id: z.string().min(1, "Please select a vital sign type"),
  value_primary: z.string().min(1, "Please enter a measurement value").transform(val => parseFloat(val)),
  value_secondary: z.string().optional().transform(val => val ? parseFloat(val) : null),
  unit: z.string().min(1, "Please select a unit"),
  measured_at: z.string().min(1, "Please enter measurement date and time"),
  measurement_method: z.enum(['manual', 'device', 'estimated']),
  device_name: z.string().optional(),
  notes: z.string().optional(),
});

export default function CreateVitalSigns({ vitalSignTypes = [] }) {
  const [selectedType, setSelectedType] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const form = useReactHookForm({
    resolver: zodResolver(formSchema),
    defaultValues: {
      vital_sign_type_id: '',
      value_primary: '',
      value_secondary: '',
      unit: '',
      measured_at: new Date().toISOString().slice(0, 16), // Current datetime in YYYY-MM-DDTHH:MM format
      measurement_method: 'manual',
      device_name: '',
      notes: '',
    },
  });

  const selectedVitalSignType = vitalSignTypes.find(
    type => type.id.toString() === form.watch('vital_sign_type_id')
  );

  // Update unit when vital sign type changes
  useEffect(() => {
    if (selectedVitalSignType) {
      form.setValue('unit', selectedVitalSignType.unit_primary);

      // Clear secondary value if not needed
      if (!selectedVitalSignType.has_secondary_value) {
        form.setValue('value_secondary', '');
      }
    }
  }, [selectedVitalSignType, form]);

  // Clear device name if method is not 'device'
  useEffect(() => {
    const method = form.watch('measurement_method');
    if (method !== 'device') {
      form.setValue('device_name', '');
    }
  }, [form.watch('measurement_method'), form]);

  const onSubmit = async (data) => {
    setIsSubmitting(true);

    try {
      const response = await fetch('/api/vital-signs', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        const errorData = await response.json();

        // Handle validation errors
        if (response.status === 422 && errorData.errors) {
          Object.keys(errorData.errors).forEach(field => {
            form.setError(field, {
              type: 'server',
              message: errorData.errors[field][0]
            });
          });
          return;
        }

        throw new Error(errorData.message || 'Failed to save vital signs');
      }

      const result = await response.json();

      // Redirect to dashboard with success message
      router.visit('/dashboard', {
        onSuccess: () => {
          // You could show a success toast here
          console.log('Vital signs saved successfully!');
        }
      });

    } catch (error) {
      console.error('Error saving vital signs:', error);
      // You could show an error toast here
    } finally {
      setIsSubmitting(false);
    }
  };

  const getAvailableUnits = () => {
    if (!selectedVitalSignType) return [];

    const units = [selectedVitalSignType.unit_primary];
    if (selectedVitalSignType.unit_secondary) {
      units.push(selectedVitalSignType.unit_secondary);
    }
    return units;
  };

  return (
    <AppLayout title="Record Vital Signs">
      <Head title="Record Vital Signs" />

      <div className="max-w-2xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Record Vital Signs</h1>
            <p className="text-muted-foreground">
              Enter your health measurements for tracking and monitoring
            </p>
          </div>
        </div>

        {/* Form */}
        <Card>
          <CardHeader>
            <CardTitle>New Measurement</CardTitle>
            <CardDescription>
              Please enter accurate measurements for the best health insights
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Form {...form}>
              <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                {/* Vital Sign Type */}
                <FormField
                  control={form.control}
                  name="vital_sign_type_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Measurement Type</FormLabel>
                      <Select onValueChange={field.onChange} defaultValue={field.value}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Select what you want to measure" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {vitalSignTypes.map((type) => (
                            <SelectItem key={type.id} value={type.id.toString()}>
                              {type.display_name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormDescription>
                        Choose the type of measurement you want to record
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Primary Value */}
                <FormField
                  control={form.control}
                  name="value_primary"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>
                        {selectedVitalSignType?.input_type === 'dual' ? 'Systolic Pressure' : 'Measurement Value'}
                      </FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          step="0.01"
                          placeholder="Enter value"
                          {...field}
                        />
                      </FormControl>
                      {selectedVitalSignType && (
                        <FormDescription>
                          Normal range: {selectedVitalSignType.normal_range_min} - {selectedVitalSignType.normal_range_max} {selectedVitalSignType.unit_primary}
                        </FormDescription>
                      )}
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Secondary Value (for blood pressure) */}
                {selectedVitalSignType?.has_secondary_value && (
                  <FormField
                    control={form.control}
                    name="value_secondary"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Diastolic Pressure</FormLabel>
                        <FormControl>
                          <Input
                            type="number"
                            step="0.01"
                            placeholder="Enter diastolic value"
                            {...field}
                          />
                        </FormControl>
                        <FormDescription>
                          The bottom number in blood pressure reading
                        </FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}

                {/* Unit */}
                <FormField
                  control={form.control}
                  name="unit"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Unit</FormLabel>
                      <Select onValueChange={field.onChange} value={field.value}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Select unit" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {getAvailableUnits().map((unit) => (
                            <SelectItem key={unit} value={unit}>
                              {unit}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Measurement Date & Time */}
                <FormField
                  control={form.control}
                  name="measured_at"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Date & Time</FormLabel>
                      <FormControl>
                        <Input
                          type="datetime-local"
                          {...field}
                        />
                      </FormControl>
                      <FormDescription>
                        When was this measurement taken?
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Measurement Method */}
                <FormField
                  control={form.control}
                  name="measurement_method"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>How was this measured?</FormLabel>
                      <Select onValueChange={field.onChange} defaultValue={field.value}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="manual">Manual measurement</SelectItem>
                          <SelectItem value="device">Medical device</SelectItem>
                          <SelectItem value="estimated">Estimated</SelectItem>
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Device Name (conditional) */}
                {form.watch('measurement_method') === 'device' && (
                  <FormField
                    control={form.control}
                    name="device_name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Device Name</FormLabel>
                        <FormControl>
                          <Input
                            placeholder="e.g., Blood pressure monitor XYZ-123"
                            {...field}
                          />
                        </FormControl>
                        <FormDescription>
                          What device was used for this measurement?
                        </FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}

                {/* Notes */}
                <FormField
                  control={form.control}
                  name="notes"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Notes (Optional)</FormLabel>
                      <FormControl>
                        <textarea
                          className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                          placeholder="Any additional notes about this measurement..."
                          {...field}
                        />
                      </FormControl>
                      <FormDescription>
                        Add any relevant context or observations
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                {/* Submit Buttons */}
                <div className="flex gap-4 pt-4">
                  <Button
                    type="submit"
                    disabled={isSubmitting}
                    className="flex-1"
                  >
                    {isSubmitting ? 'Saving...' : 'Save Measurement'}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => router.visit('/dashboard')}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            </Form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}