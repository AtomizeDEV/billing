import ApplicationSerializer from '@atomizedev/ember-core/serializers/application';

export default class PlanSerializer extends ApplicationSerializer {
    modelNameFromPayloadKey(key) {
        let modelName = super.modelNameFromPayloadKey(key);

        if (modelName.startsWith('billing-')) {
            modelName = modelName.replace('billing-', '');
        }

        return modelName;
    }
}
