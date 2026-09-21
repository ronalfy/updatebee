/**
 * Plugin auto-update configure menu.
 */
import AssetUpdateConfigure from './AssetUpdateConfigure';

export {
	applyPluginAutoUpdatesDisabledAll,
	applyPluginAutoUpdatesEnabled,
} from './AssetUpdateConfigure';

const PluginUpdateConfigure = () => {
	return <AssetUpdateConfigure kind="plugin" />;
};

export default PluginUpdateConfigure;
