import License from './License';
import Checkbox from './Checkbox';
import TrackingTest from './TrackingTest';
import Email from './Email';
import Password from './Password';
import Plugins from './Plugins';
import QrCode from './QrCode';
import TwoFaValidation from './TwoFaValidation';
import BackupCodes from './BackupCodes';
import Dropdown from './Dropdown';
import NumberInputWithControls from './NumberInputWithControls';
import MultiSelectDropdown from './MultiSelectDropdown';
import Text from './Text';
import Textarea from './Textarea';
import { ErrorBoundary } from '../ErrorBoundary';
// @ts-ignore
import useOnboardingStore from "@/store/useOnboardingStore";
// @ts-ignore
import useAlertStore from "@/store/useAlertStore";
import ButtonInput from '../Inputs/ButtonInput';
import Alert from '../Alert';

type FieldsProps = {
    fields: any[];
    onChange: (id: string, value: any) => void;
    fieldStatus?: (id: string, success: boolean) => void;
};
/**
 * Fields component that renders different field types based on the field configuration
 * @param {Object} props Component props
 * @param {Array} props.fields Array of field configurations
 * @param {Function} props.onChange Callback function when field values change
 * @param {Function} props.fieldStatus Callback function when success status of field changes
 * @returns {JSX.Element|null} The rendered fields or null if no fields
 */
const Fields = ({ fields, onChange, fieldStatus = () => {} }:FieldsProps ) => {
    const {
        getValue,
        setValue,
        isEdited,
        settings,
    } = useOnboardingStore();

    const { getAlertState, setAlertState } = useAlertStore();

    if (!fields) return null;

    const fieldComponents = {
        two_fa_validation: TwoFaValidation,
        qr_code: QrCode,
        backup_codes: BackupCodes,
        license: License,
        checkbox: Checkbox,
        tracking_test: TrackingTest,
        email: Email,
        plugins: Plugins,
        password: Password,
        dropdown: Dropdown,
        number: NumberInputWithControls,
        multi_select: MultiSelectDropdown,
        button: ButtonInput,
        text: Text,
        textarea: Textarea,
    };

    //the settings contain the values.
    return (
        <ErrorBoundary>
            {fields.map((field) => {
                let value = getValue(field.id);
                const isEditedField = isEdited(field.id);
                if (!isEditedField && (value === undefined || value === null) && field.default !== undefined) {
                    const disabled = field.is_lock === true;

                    if (disabled) {
                        setValue(field.id, false);
                        value = false;
                    } else {
                        setValue(field.id, field.default);
                        value = field.default;
                    }
                }
                const Component = fieldComponents[field.type] || null;

                if (field.type === 'button') {
                    const groupId = field.group_id;
                    const alertState = getAlertState(groupId);

                    const handleButtonClick = () => {
                        const externalActionName = field.externalAction;
                        const externalAction = (window as any).pluginOnboardingActions?.[externalActionName];

                        if (typeof externalAction === 'function') {
                            externalAction(
                                field,
                                settings,
                                (id: string, newState: any) => setAlertState(id, newState),
                                setValue
                            );
                        } else {
                            console.warn(`External action '${externalActionName}' not found.`);
                            setAlertState(groupId, {
                                responseMessage: `Action '${externalActionName}' is not implemented.`,
                                responseSuccess: false,
                                responseCode: 'danger',
                                isUpdating: false,
                            });
                            fieldStatus(field.id, false);
                        }
                    };

                    // Determine variant and message for Alert
                    const alertVariant = alertState.responseCode === 'loading'
                        ? 'loading'
                        : (alertState.responseCode === 'success' ? 'success' : 'danger');
                    const alertMessage = alertState.responseMessage;

                    return (
                        <div key={`${field.id}-wrapper`} className="!mt-6">
                            {field.actionType === 'connection_test' && alertState.responseMessage && (
                                <Alert
                                    variant={alertVariant}
                                    message={alertMessage}
                                    className="mb-4"
                                />
                            )}
                            <ButtonInput
                                onClick={handleButtonClick}
                                btnVariant="secondary"
                                size="md"
                                className="w-full"
                                disabled={alertState.isUpdating}
                            >
                                {field.label}
                            </ButtonInput>
                        </div>
                    );
                }

                const commonProps = {
                    key: field.id,
                    field: field,
                    onChange: (value: any) => onChange(field.id, value),
                    fieldStatus: (success: boolean) => fieldStatus(field.id, success),
                    value: value,
                };

                return Component
                    ? <Component
                        {...commonProps}
                      />
                    : null;
            })}
        </ErrorBoundary>
    );
};

export default Fields;